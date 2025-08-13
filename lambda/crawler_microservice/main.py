"""
Social Media Crawler Micro-Service - AWS Lambda Implementation
Crawls Twitter/X, Reddit, and Telegram with keyword rules
"""

import json
import os
import time
import logging
from typing import Dict, List, Any, Optional
from datetime import datetime, timedelta
import asyncio
import aiohttp
import boto3
from dataclasses import dataclass, asdict
import praw
import tweepy
from telegram import Bot
import requests

# Configure logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

@dataclass
class CrawlJob:
    """Represents a crawling job configuration"""
    job_id: str
    platforms: List[str]
    keyword_rules: List[str]
    max_posts: int = 100
    priority: str = 'normal'
    callback_url: Optional[str] = None

@dataclass
class CrawlResult:
    """Represents crawling results"""
    job_id: str
    platform: str
    posts_found: int
    keyword_matches: int
    processing_time_ms: int
    status: str
    error: Optional[str] = None

class SocialMediaCrawler:
    """Main crawler orchestrator"""
    
    def __init__(self):
        self.config = self._load_config()
        self.session = None
        
    def _load_config(self) -> Dict[str, Any]:
        """Load configuration from environment variables"""
        return {
            'twitter': {
                'bearer_token': os.getenv('TWITTER_BEARER_TOKEN'),
                'api_key': os.getenv('TWITTER_API_KEY'),
                'api_secret': os.getenv('TWITTER_API_SECRET'),
                'access_token': os.getenv('TWITTER_ACCESS_TOKEN'),
                'access_token_secret': os.getenv('TWITTER_ACCESS_TOKEN_SECRET'),
                'rate_limit_delay': float(os.getenv('TWITTER_RATE_LIMIT_DELAY', '1.0'))
            },
            'reddit': {
                'client_id': os.getenv('REDDIT_CLIENT_ID'),
                'client_secret': os.getenv('REDDIT_CLIENT_SECRET'),
                'username': os.getenv('REDDIT_USERNAME'),
                'password': os.getenv('REDDIT_PASSWORD'),
                'user_agent': os.getenv('REDDIT_USER_AGENT', 'CrawlerBot/1.0'),
                'subreddits': os.getenv('REDDIT_SUBREDDITS', 'cryptocurrency,defi,ethereum').split(','),
                'rate_limit_delay': float(os.getenv('REDDIT_RATE_LIMIT_DELAY', '2.0'))
            },
            'telegram': {
                'bot_token': os.getenv('TELEGRAM_BOT_TOKEN'),
                'channels': os.getenv('TELEGRAM_CHANNELS', '').split(',') if os.getenv('TELEGRAM_CHANNELS') else [],
                'rate_limit_delay': float(os.getenv('TELEGRAM_RATE_LIMIT_DELAY', '0.5'))
            },
            'proxy': {
                'enabled': os.getenv('PROXY_ENABLED', 'true').lower() == 'true',
                'url': os.getenv('PROXY_URL', 'socks5://192.168.1.32:8086')
            },
            'aws': {
                'region': os.getenv('AWS_REGION', 'us-east-1'),
                'dynamodb_table': os.getenv('DYNAMODB_TABLE', 'social-media-posts'),
                'sns_topic': os.getenv('SNS_TOPIC_ARN')
            }
        }

    async def execute_crawl_job(self, job: CrawlJob) -> Dict[str, Any]:
        """Execute a complete crawling job"""
        logger.info(f"Starting crawl job {job.job_id} for platforms: {job.platforms}")
        
        start_time = time.time()
        results = {
            'job_id': job.job_id,
            'started_at': datetime.utcnow().isoformat(),
            'platforms': {},
            'total_posts': 0,
            'total_matches': 0,
            'errors': []
        }
        
        try:
            # Create aiohttp session for async requests
            async with aiohttp.ClientSession(
                connector=self._get_proxy_connector()
            ) as session:
                self.session = session
                
                # Process each platform
                tasks = []
                for platform in job.platforms:
                    if self._is_platform_enabled(platform):
                        task = self._crawl_platform(platform, job.keyword_rules, job.max_posts)
                        tasks.append(task)
                
                platform_results = await asyncio.gather(*tasks, return_exceptions=True)
                
                # Process results
                for i, result in enumerate(platform_results):
                    platform = job.platforms[i] if i < len(job.platforms) else 'unknown'
                    
                    if isinstance(result, Exception):
                        logger.error(f"Platform {platform} failed: {str(result)}")
                        results['platforms'][platform] = {
                            'status': 'error',
                            'error': str(result),
                            'posts_found': 0,
                            'keyword_matches': 0
                        }
                        results['errors'].append(f"{platform}: {str(result)}")
                    else:
                        results['platforms'][platform] = result
                        results['total_posts'] += result.get('posts_found', 0)
                        results['total_matches'] += result.get('keyword_matches', 0)
            
            processing_time = int((time.time() - start_time) * 1000)
            results['processing_time_ms'] = processing_time
            results['completed_at'] = datetime.utcnow().isoformat()
            results['status'] = 'completed' if not results['errors'] else 'completed_with_errors'
            
            # Store results and send notifications
            await self._store_results(results)
            await self._send_notifications(job, results)
            
            logger.info(f"Job {job.job_id} completed. Posts: {results['total_posts']}, Matches: {results['total_matches']}")
            
        except Exception as e:
            logger.error(f"Job {job.job_id} failed: {str(e)}")
            results['status'] = 'failed'
            results['error'] = str(e)
            results['completed_at'] = datetime.utcnow().isoformat()
        
        return results

    async def _crawl_platform(self, platform: str, keywords: List[str], max_posts: int) -> Dict[str, Any]:
        """Crawl a specific platform"""
        start_time = time.time()
        
        try:
            if platform == 'twitter':
                result = await self._crawl_twitter(keywords, max_posts)
            elif platform == 'reddit':
                result = await self._crawl_reddit(keywords, max_posts)
            elif platform == 'telegram':
                result = await self._crawl_telegram(keywords, max_posts)
            else:
                raise ValueError(f"Unsupported platform: {platform}")
            
            processing_time = int((time.time() - start_time) * 1000)
            result['processing_time_ms'] = processing_time
            result['status'] = 'success'
            
            return result
            
        except Exception as e:
            logger.error(f"Failed to crawl {platform}: {str(e)}")
            return {
                'status': 'error',
                'error': str(e),
                'posts_found': 0,
                'keyword_matches': 0,
                'processing_time_ms': int((time.time() - start_time) * 1000)
            }

    async def _crawl_twitter(self, keywords: List[str], max_posts: int) -> Dict[str, Any]:
        """Crawl Twitter using API v2"""
        config = self.config['twitter']
        
        if not config['bearer_token']:
            raise ValueError("Twitter Bearer Token not configured")
        
        posts = []
        keyword_matches = 0
        
        # Setup Twitter API client
        client = tweepy.Client(
            bearer_token=config['bearer_token'],
            wait_on_rate_limit=True
        )
        
        posts_per_keyword = max(1, max_posts // len(keywords))
        
        for keyword in keywords:
            try:
                # Search for tweets
                tweets = tweepy.Paginator(
                    client.search_recent_tweets,
                    query=f"{keyword} -is:retweet lang:en",
                    max_results=min(posts_per_keyword, 100),
                    tweet_fields=['created_at', 'author_id', 'public_metrics', 'context_annotations']
                ).flatten(limit=posts_per_keyword)
                
                for tweet in tweets:
                    post_data = {
                        'id': tweet.id,
                        'content': tweet.text,
                        'author': str(tweet.author_id),
                        'created_at': tweet.created_at.isoformat() if tweet.created_at else None,
                        'url': f"https://twitter.com/i/status/{tweet.id}",
                        'metrics': tweet.public_metrics or {},
                        'keyword_match': keyword,
                        'platform': 'twitter'
                    }
                    
                    posts.append(post_data)
                    keyword_matches += 1
                    
                    if len(posts) >= max_posts:
                        break
                
                # Rate limiting
                await asyncio.sleep(config['rate_limit_delay'])
                
            except Exception as e:
                logger.warning(f"Twitter search failed for keyword '{keyword}': {str(e)}")
                continue
        
        return {
            'posts_found': len(posts),
            'keyword_matches': keyword_matches,
            'posts': posts[:max_posts]
        }

    async def _crawl_reddit(self, keywords: List[str], max_posts: int) -> Dict[str, Any]:
        """Crawl Reddit using PRAW"""
        config = self.config['reddit']
        
        if not all([config['client_id'], config['client_secret'], config['username'], config['password']]):
            raise ValueError("Reddit credentials not configured")
        
        posts = []
        keyword_matches = 0
        
        # Setup Reddit API client
        reddit = praw.Reddit(
            client_id=config['client_id'],
            client_secret=config['client_secret'],
            username=config['username'],
            password=config['password'],
            user_agent=config['user_agent']
        )
        
        subreddits = config['subreddits']
        
        for subreddit_name in subreddits:
            for keyword in keywords:
                try:
                    subreddit = reddit.subreddit(subreddit_name)
                    
                    # Search in subreddit
                    submissions = subreddit.search(
                        keyword,
                        sort='new',
                        limit=25,
                        time_filter='day'
                    )
                    
                    for submission in submissions:
                        post_data = {
                            'id': submission.id,
                            'content': f"{submission.title}\n{submission.selftext}"[:1000],
                            'author': str(submission.author) if submission.author else 'deleted',
                            'created_at': datetime.fromtimestamp(submission.created_utc).isoformat(),
                            'url': f"https://reddit.com{submission.permalink}",
                            'metrics': {
                                'score': submission.score,
                                'upvote_ratio': submission.upvote_ratio,
                                'num_comments': submission.num_comments
                            },
                            'keyword_match': keyword,
                            'platform': 'reddit',
                            'subreddit': subreddit_name
                        }
                        
                        posts.append(post_data)
                        keyword_matches += 1
                        
                        if len(posts) >= max_posts:
                            break
                    
                    if len(posts) >= max_posts:
                        break
                    
                    # Rate limiting
                    await asyncio.sleep(config['rate_limit_delay'])
                    
                except Exception as e:
                    logger.warning(f"Reddit search failed for '{keyword}' in r/{subreddit_name}: {str(e)}")
                    continue
            
            if len(posts) >= max_posts:
                break
        
        return {
            'posts_found': len(posts),
            'keyword_matches': keyword_matches,
            'posts': posts[:max_posts]
        }

    async def _crawl_telegram(self, keywords: List[str], max_posts: int) -> Dict[str, Any]:
        """Crawl Telegram using Bot API"""
        config = self.config['telegram']
        
        if not config['bot_token']:
            raise ValueError("Telegram Bot Token not configured")
        
        if not config['channels']:
            raise ValueError("No Telegram channels configured")
        
        posts = []
        keyword_matches = 0
        
        # Setup Telegram Bot
        bot = Bot(token=config['bot_token'])
        
        for channel in config['channels']:
            try:
                # Get recent updates (limited by Bot API constraints)
                updates = await bot.get_updates(
                    allowed_updates=['channel_post'],
                    limit=100
                )
                
                for update in updates:
                    if update.channel_post:
                        message = update.channel_post
                        content = message.text or message.caption or ''
                        
                        # Check if any keyword matches
                        matching_keywords = [kw for kw in keywords if kw.lower() in content.lower()]
                        
                        if matching_keywords:
                            post_data = {
                                'id': message.message_id,
                                'content': content[:1000],
                                'author': channel,
                                'created_at': message.date.isoformat(),
                                'url': f"https://t.me/{channel}/{message.message_id}",
                                'metrics': {},
                                'keyword_matches': matching_keywords,
                                'platform': 'telegram',
                                'channel': channel
                            }
                            
                            posts.append(post_data)
                            keyword_matches += len(matching_keywords)
                            
                            if len(posts) >= max_posts:
                                break
                
                if len(posts) >= max_posts:
                    break
                
                # Rate limiting
                await asyncio.sleep(config['rate_limit_delay'])
                
            except Exception as e:
                logger.warning(f"Telegram crawl failed for channel '{channel}': {str(e)}")
                continue
        
        return {
            'posts_found': len(posts),
            'keyword_matches': keyword_matches,
            'posts': posts[:max_posts]
        }

    def _get_proxy_connector(self):
        """Get aiohttp connector with proxy configuration"""
        if not self.config['proxy']['enabled']:
            return None
        
        proxy_url = self.config['proxy']['url']
        
        # For socks5 proxy, you'd need aiohttp-socks
        # return ProxyConnector.from_url(proxy_url)
        return None  # Simplified for this example

    def _is_platform_enabled(self, platform: str) -> bool:
        """Check if platform is enabled and configured"""
        config = self.config.get(platform, {})
        
        if platform == 'twitter':
            return bool(config.get('bearer_token'))
        elif platform == 'reddit':
            return all([
                config.get('client_id'),
                config.get('client_secret'),
                config.get('username'),
                config.get('password')
            ])
        elif platform == 'telegram':
            return bool(config.get('bot_token')) and bool(config.get('channels'))
        
        return False

    async def _store_results(self, results: Dict[str, Any]) -> None:
        """Store results in DynamoDB"""
        try:
            dynamodb = boto3.resource('dynamodb', region_name=self.config['aws']['region'])
            table = dynamodb.Table(self.config['aws']['dynamodb_table'])
            
            # Store job results
            table.put_item(
                Item={
                    'job_id': results['job_id'],
                    'type': 'job_result',
                    'timestamp': results['completed_at'],
                    'data': json.dumps(results),
                    'ttl': int(time.time()) + (30 * 24 * 60 * 60)  # 30 days TTL
                }
            )
            
            # Store individual posts
            for platform, platform_data in results['platforms'].items():
                if 'posts' in platform_data:
                    for post in platform_data['posts']:
                        table.put_item(
                            Item={
                                'post_id': f"{platform}_{post['id']}",
                                'type': 'social_post',
                                'platform': platform,
                                'job_id': results['job_id'],
                                'timestamp': post.get('created_at', results['completed_at']),
                                'content': post['content'],
                                'author': post['author'],
                                'url': post['url'],
                                'metrics': json.dumps(post.get('metrics', {})),
                                'keyword_matches': json.dumps(post.get('keyword_matches', [])),
                                'ttl': int(time.time()) + (90 * 24 * 60 * 60)  # 90 days TTL
                            }
                        )
            
            logger.info(f"Stored results for job {results['job_id']} in DynamoDB")
            
        except Exception as e:
            logger.error(f"Failed to store results: {str(e)}")

    async def _send_notifications(self, job: CrawlJob, results: Dict[str, Any]) -> None:
        """Send notifications about job completion"""
        try:
            # Send SNS notification
            if self.config['aws']['sns_topic']:
                sns = boto3.client('sns', region_name=self.config['aws']['region'])
                
                message = {
                    'job_id': job.job_id,
                    'status': results['status'],
                    'total_posts': results['total_posts'],
                    'total_matches': results['total_matches'],
                    'platforms': list(results['platforms'].keys()),
                    'processing_time_ms': results.get('processing_time_ms', 0),
                    'errors': results.get('errors', [])
                }
                
                sns.publish(
                    TopicArn=self.config['aws']['sns_topic'],
                    Subject=f"Crawler Job {job.job_id} - {results['status']}",
                    Message=json.dumps(message, indent=2)
                )
            
            # Send callback webhook if configured
            if job.callback_url:
                callback_data = {
                    'job_id': job.job_id,
                    'status': results['status'],
                    'results': results
                }
                
                async with aiohttp.ClientSession() as session:
                    await session.post(
                        job.callback_url,
                        json=callback_data,
                        timeout=aiohttp.ClientTimeout(total=10)
                    )
            
            logger.info(f"Sent notifications for job {job.job_id}")
            
        except Exception as e:
            logger.error(f"Failed to send notifications: {str(e)}")


# AWS Lambda Handler
def lambda_handler(event, context):
    """AWS Lambda entry point"""
    try:
        # Parse the event
        if isinstance(event.get('body'), str):
            body = json.loads(event['body'])
        else:
            body = event.get('body', event)
        
        # Create crawl job from event
        job = CrawlJob(
            job_id=body.get('job_id', f"lambda_{int(time.time())}"),
            platforms=body.get('platforms', ['twitter', 'reddit']),
            keyword_rules=body.get('keyword_rules', []),
            max_posts=body.get('max_posts', 100),
            priority=body.get('priority', 'normal'),
            callback_url=body.get('callback_url')
        )
        
        # Execute crawling
        crawler = SocialMediaCrawler()
        
        # Run the async function
        loop = asyncio.new_event_loop()
        asyncio.set_event_loop(loop)
        
        try:
            results = loop.run_until_complete(crawler.execute_crawl_job(job))
        finally:
            loop.close()
        
        # Return response
        response = {
            'statusCode': 200,
            'headers': {
                'Content-Type': 'application/json',
                'Access-Control-Allow-Origin': '*'
            },
            'body': json.dumps({
                'success': True,
                'results': results
            })
        }
        
        return response
        
    except Exception as e:
        logger.error(f"Lambda execution failed: {str(e)}")
        
        return {
            'statusCode': 500,
            'headers': {
                'Content-Type': 'application/json',
                'Access-Control-Allow-Origin': '*'
            },
            'body': json.dumps({
                'success': False,
                'error': str(e),
                'timestamp': datetime.utcnow().isoformat()
            })
        }


# For local testing
if __name__ == "__main__":
    # Test locally
    test_event = {
        'body': {
            'job_id': 'test_local',
            'platforms': ['twitter'],
            'keyword_rules': ['bitcoin', 'ethereum'],
            'max_posts': 10
        }
    }
    
    result = lambda_handler(test_event, None)
    print(json.dumps(result, indent=2))