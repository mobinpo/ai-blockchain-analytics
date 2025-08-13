#!/usr/bin/env python3
"""
AWS Lambda Social Media Crawler
Pulls content from Twitter/X, Reddit, and Telegram with keyword rules
"""

import json
import os
import logging
import boto3
import requests
import time
from datetime import datetime, timedelta
from typing import Dict, List, Optional, Any
import hashlib
import re
from dataclasses import dataclass, asdict
from urllib.parse import quote_plus
import asyncio
import aiohttp

# Configure logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

@dataclass
class CrawlResult:
    """Data structure for crawl results"""
    external_id: str
    platform: str
    source_url: str
    author_username: Optional[str]
    author_display_name: Optional[str]
    content: str
    media_urls: List[str]
    engagement_count: int
    share_count: int
    comment_count: int
    published_at: str
    raw_data: Dict[str, Any]
    matched_keywords: List[str]

@dataclass
class KeywordRule:
    """Keyword matching rule"""
    id: str
    name: str
    keywords: List[str]
    exclude_keywords: List[str]
    match_type: str  # 'any', 'all', 'exact', 'regex'
    case_sensitive: bool
    platforms: List[str]
    priority: int

class RateLimitManager:
    """Manages API rate limits across platforms"""
    
    def __init__(self):
        self.limits = {
            'twitter': {'requests': 300, 'window': 900},  # 300 per 15 min
            'reddit': {'requests': 100, 'window': 600},   # 100 per 10 min
            'telegram': {'requests': 30, 'window': 60},   # 30 per minute
        }
        self.redis_client = None
        self._init_redis()
    
    def _init_redis(self):
        """Initialize Redis connection for rate limiting"""
        try:
            import redis
            redis_url = os.environ.get('REDIS_URL')
            if redis_url:
                self.redis_client = redis.from_url(redis_url)
        except ImportError:
            logger.warning("Redis not available, using in-memory rate limiting")
    
    def can_make_request(self, platform: str, endpoint: str) -> bool:
        """Check if request is within rate limits"""
        key = f"rate_limit:{platform}:{endpoint}"
        
        if self.redis_client:
            try:
                current = self.redis_client.get(key)
                if current is None:
                    return True
                return int(current) < self.limits[platform]['requests']
            except Exception as e:
                logger.warning(f"Redis error: {e}")
        
        return True  # Allow if can't check
    
    def record_request(self, platform: str, endpoint: str):
        """Record API request for rate limiting"""
        key = f"rate_limit:{platform}:{endpoint}"
        window = self.limits[platform]['window']
        
        if self.redis_client:
            try:
                pipe = self.redis_client.pipeline()
                pipe.incr(key)
                pipe.expire(key, window)
                pipe.execute()
            except Exception as e:
                logger.warning(f"Redis error: {e}")

class TwitterCrawler:
    """Twitter/X API crawler"""
    
    def __init__(self, bearer_token: str):
        self.bearer_token = bearer_token
        self.base_url = "https://api.twitter.com/2"
        self.rate_manager = RateLimitManager()
    
    async def search_tweets(self, keywords: List[str], max_results: int = 100, 
                           hours_back: int = 24) -> List[CrawlResult]:
        """Search tweets with keywords"""
        if not self.rate_manager.can_make_request('twitter', 'search'):
            logger.warning("Twitter rate limit exceeded")
            return []
        
        # Build search query
        query = self._build_search_query(keywords)
        start_time = (datetime.utcnow() - timedelta(hours=hours_back)).isoformat() + 'Z'
        
        url = f"{self.base_url}/tweets/search/recent"
        params = {
            'query': query,
            'max_results': min(max_results, 100),
            'start_time': start_time,
            'expansions': 'author_id,attachments.media_keys',
            'tweet.fields': 'created_at,public_metrics,context_annotations,lang',
            'user.fields': 'username,name,verified,public_metrics',
            'media.fields': 'url,preview_image_url,type'
        }
        
        headers = {
            'Authorization': f'Bearer {self.bearer_token}',
            'User-Agent': 'BlockchainCrawlerLambda/1.0'
        }
        
        async with aiohttp.ClientSession() as session:
            try:
                async with session.get(url, params=params, headers=headers) as response:
                    self.rate_manager.record_request('twitter', 'search')
                    
                    if response.status == 200:
                        data = await response.json()
                        return self._transform_tweets(data, keywords)
                    elif response.status == 429:
                        logger.warning("Twitter rate limit hit")
                        return []
                    else:
                        logger.error(f"Twitter API error: {response.status}")
                        return []
                        
            except Exception as e:
                logger.error(f"Twitter API request failed: {e}")
                return []
    
    def _build_search_query(self, keywords: List[str]) -> str:
        """Build Twitter search query from keywords"""
        escaped_keywords = [f'"{keyword}"' for keyword in keywords]
        query = '(' + ' OR '.join(escaped_keywords) + ')'
        query += ' -is:retweet lang:en'
        return query
    
    def _transform_tweets(self, data: Dict, keywords: List[str]) -> List[CrawlResult]:
        """Transform Twitter API response to CrawlResult objects"""
        tweets = data.get('data', [])
        users = {user['id']: user for user in data.get('includes', {}).get('users', [])}
        media = {item['media_key']: item for item in data.get('includes', {}).get('media', [])}
        
        results = []
        for tweet in tweets:
            author = users.get(tweet.get('author_id'), {})
            tweet_media = self._extract_media_urls(tweet, media)
            matched_kw = self._find_matching_keywords(tweet.get('text', ''), keywords)
            
            result = CrawlResult(
                external_id=tweet['id'],
                platform='twitter',
                source_url=f"https://twitter.com/{author.get('username', 'unknown')}/status/{tweet['id']}",
                author_username=author.get('username'),
                author_display_name=author.get('name'),
                content=tweet.get('text', ''),
                media_urls=tweet_media,
                engagement_count=tweet.get('public_metrics', {}).get('like_count', 0),
                share_count=tweet.get('public_metrics', {}).get('retweet_count', 0),
                comment_count=tweet.get('public_metrics', {}).get('reply_count', 0),
                published_at=tweet.get('created_at'),
                raw_data=tweet,
                matched_keywords=matched_kw
            )
            results.append(result)
        
        return results
    
    def _extract_media_urls(self, tweet: Dict, media_index: Dict) -> List[str]:
        """Extract media URLs from tweet"""
        urls = []
        attachments = tweet.get('attachments', {})
        media_keys = attachments.get('media_keys', [])
        
        for key in media_keys:
            media_item = media_index.get(key, {})
            url = media_item.get('url') or media_item.get('preview_image_url')
            if url:
                urls.append(url)
        
        return urls
    
    def _find_matching_keywords(self, text: str, keywords: List[str]) -> List[str]:
        """Find which keywords match in the text"""
        text_lower = text.lower()
        matched = []
        
        for keyword in keywords:
            if keyword.lower() in text_lower:
                matched.append(keyword)
        
        return matched

class RedditCrawler:
    """Reddit API crawler"""
    
    def __init__(self, client_id: str, client_secret: str, user_agent: str):
        self.client_id = client_id
        self.client_secret = client_secret
        self.user_agent = user_agent
        self.access_token = None
        self.rate_manager = RateLimitManager()
        self.default_subreddits = [
            'cryptocurrency', 'bitcoin', 'ethereum', 'defi', 'NFT',
            'CryptoMarkets', 'BlockChain', 'CryptoCurrencyTrading'
        ]
    
    async def authenticate(self):
        """Authenticate with Reddit API"""
        url = 'https://www.reddit.com/api/v1/access_token'
        auth = aiohttp.BasicAuth(self.client_id, self.client_secret)
        data = {'grant_type': 'client_credentials'}
        headers = {'User-Agent': self.user_agent}
        
        async with aiohttp.ClientSession() as session:
            try:
                async with session.post(url, auth=auth, data=data, headers=headers) as response:
                    if response.status == 200:
                        token_data = await response.json()
                        self.access_token = token_data['access_token']
                        logger.info("Reddit authentication successful")
                    else:
                        logger.error(f"Reddit auth failed: {response.status}")
            except Exception as e:
                logger.error(f"Reddit authentication error: {e}")
    
    async def search_posts(self, keywords: List[str], max_results: int = 100,
                          hours_back: int = 24) -> List[CrawlResult]:
        """Search Reddit posts"""
        if not self.access_token:
            await self.authenticate()
        
        if not self.access_token or not self.rate_manager.can_make_request('reddit', 'search'):
            return []
        
        results = []
        
        # Search each keyword
        for keyword in keywords:
            if len(results) >= max_results:
                break
                
            posts = await self._search_keyword(keyword, min(25, max_results - len(results)))
            results.extend(posts)
        
        # Also check specific subreddits
        for subreddit in self.default_subreddits[:3]:  # Limit to avoid rate limits
            if len(results) >= max_results:
                break
                
            posts = await self._get_subreddit_posts(subreddit, keywords, 10)
            results.extend(posts)
        
        return results[:max_results]
    
    async def _search_keyword(self, keyword: str, limit: int) -> List[CrawlResult]:
        """Search Reddit for specific keyword"""
        url = 'https://oauth.reddit.com/search'
        headers = {
            'Authorization': f'Bearer {self.access_token}',
            'User-Agent': self.user_agent
        }
        params = {
            'q': keyword,
            'type': 'link',
            'sort': 'new',
            'limit': limit,
            'restrict_sr': False
        }
        
        async with aiohttp.ClientSession() as session:
            try:
                async with session.get(url, params=params, headers=headers) as response:
                    self.rate_manager.record_request('reddit', 'search')
                    
                    if response.status == 200:
                        data = await response.json()
                        return self._transform_reddit_posts(data, [keyword])
                    else:
                        logger.error(f"Reddit search error: {response.status}")
                        return []
                        
            except Exception as e:
                logger.error(f"Reddit search failed: {e}")
                return []
    
    async def _get_subreddit_posts(self, subreddit: str, keywords: List[str], limit: int) -> List[CrawlResult]:
        """Get posts from specific subreddit"""
        url = f'https://oauth.reddit.com/r/{subreddit}/new'
        headers = {
            'Authorization': f'Bearer {self.access_token}',
            'User-Agent': self.user_agent
        }
        params = {'limit': limit}
        
        async with aiohttp.ClientSession() as session:
            try:
                async with session.get(url, params=params, headers=headers) as response:
                    self.rate_manager.record_request('reddit', 'subreddit')
                    
                    if response.status == 200:
                        data = await response.json()
                        all_posts = self._transform_reddit_posts(data, keywords)
                        # Filter posts that contain keywords
                        return [post for post in all_posts if post.matched_keywords]
                    else:
                        logger.error(f"Reddit subreddit error: {response.status}")
                        return []
                        
            except Exception as e:
                logger.error(f"Reddit subreddit request failed: {e}")
                return []
    
    def _transform_reddit_posts(self, data: Dict, keywords: List[str]) -> List[CrawlResult]:
        """Transform Reddit API response to CrawlResult objects"""
        posts = []
        children = data.get('data', {}).get('children', [])
        
        for child in children:
            if child['kind'] != 't3':  # Only process posts, not comments
                continue
                
            post = child['data']
            content = self._get_post_content(post)
            matched_kw = self._find_matching_keywords(content, keywords)
            
            result = CrawlResult(
                external_id=post['id'],
                platform='reddit',
                source_url='https://reddit.com' + post['permalink'],
                author_username=post['author'],
                author_display_name=post['author'],
                content=content,
                media_urls=self._extract_reddit_media(post),
                engagement_count=post.get('score', 0),
                share_count=0,
                comment_count=post.get('num_comments', 0),
                published_at=datetime.fromtimestamp(post['created_utc']).isoformat() + 'Z',
                raw_data=post,
                matched_keywords=matched_kw
            )
            posts.append(result)
        
        return posts
    
    def _get_post_content(self, post: Dict) -> str:
        """Extract content from Reddit post"""
        title = post.get('title', '')
        selftext = post.get('selftext', '')
        url = post.get('url', '')
        
        content = title
        if selftext:
            content += "\n\n" + selftext
        elif url and not 'reddit.com' in url:
            content += "\n\n" + url
        
        return content.strip()
    
    def _extract_reddit_media(self, post: Dict) -> List[str]:
        """Extract media URLs from Reddit post"""
        urls = []
        
        # Direct image/video URL
        post_url = post.get('url', '')
        if self._is_media_url(post_url):
            urls.append(post_url)
        
        # Preview images
        preview = post.get('preview', {})
        if 'images' in preview and preview['images']:
            image_url = preview['images'][0].get('source', {}).get('url')
            if image_url:
                urls.append(image_url.replace('&amp;', '&'))
        
        return list(set(urls))
    
    def _is_media_url(self, url: str) -> bool:
        """Check if URL points to media content"""
        if not url:
            return False
            
        media_domains = ['i.redd.it', 'v.redd.it', 'imgur.com', 'gfycat.com']
        media_extensions = ['.jpg', '.jpeg', '.png', '.gif', '.webp', '.mp4']
        
        return any(domain in url for domain in media_domains) or \
               any(url.lower().endswith(ext) for ext in media_extensions)
    
    def _find_matching_keywords(self, text: str, keywords: List[str]) -> List[str]:
        """Find matching keywords in text"""
        text_lower = text.lower()
        return [kw for kw in keywords if kw.lower() in text_lower]

class TelegramCrawler:
    """Telegram Bot API crawler"""
    
    def __init__(self, bot_token: str, monitored_channels: List[str]):
        self.bot_token = bot_token
        self.monitored_channels = monitored_channels
        self.rate_manager = RateLimitManager()
        self.base_url = f"https://api.telegram.org/bot{bot_token}"
    
    async def get_updates(self, keywords: List[str], max_results: int = 50) -> List[CrawlResult]:
        """Get updates from monitored Telegram channels"""
        # Note: This is simplified - production would need webhook or Client API
        logger.info(f"Telegram crawler would monitor {len(self.monitored_channels)} channels")
        return []  # Placeholder - requires webhook setup or Client API

class KeywordMatcher:
    """Matches content against keyword rules"""
    
    @staticmethod
    def matches_rule(content: str, rule: KeywordRule) -> List[str]:
        """Check if content matches keyword rule"""
        text = content if rule.case_sensitive else content.lower()
        keywords = rule.keywords if rule.case_sensitive else [kw.lower() for kw in rule.keywords]
        exclude_keywords = rule.exclude_keywords if rule.case_sensitive else [kw.lower() for kw in rule.exclude_keywords]
        
        # Check exclusions first
        if exclude_keywords and any(ekw in text for ekw in exclude_keywords):
            return []
        
        matched = []
        
        if rule.match_type == 'any':
            matched = [kw for kw in keywords if kw in text]
        elif rule.match_type == 'all':
            if all(kw in text for kw in keywords):
                matched = keywords
        elif rule.match_type == 'exact':
            matched = [kw for kw in keywords if kw == text.strip()]
        elif rule.match_type == 'regex':
            for pattern in keywords:
                try:
                    if re.search(pattern, text):
                        matched.append(pattern)
                except re.error:
                    continue
        
        return matched

class SocialCrawlerLambda:
    """Main Lambda handler class"""
    
    def __init__(self):
        self.twitter_crawler = None
        self.reddit_crawler = None
        self.telegram_crawler = None
        self.dynamodb = boto3.resource('dynamodb')
        self.s3 = boto3.client('s3')
        
        self._init_crawlers()
    
    def _init_crawlers(self):
        """Initialize platform crawlers"""
        # Twitter
        twitter_token = os.environ.get('TWITTER_BEARER_TOKEN')
        if twitter_token:
            self.twitter_crawler = TwitterCrawler(twitter_token)
        
        # Reddit
        reddit_id = os.environ.get('REDDIT_CLIENT_ID')
        reddit_secret = os.environ.get('REDDIT_CLIENT_SECRET')
        if reddit_id and reddit_secret:
            self.reddit_crawler = RedditCrawler(
                reddit_id, reddit_secret, 
                'BlockchainCrawlerLambda/1.0'
            )
        
        # Telegram
        telegram_token = os.environ.get('TELEGRAM_BOT_TOKEN')
        channels = os.environ.get('TELEGRAM_CHANNELS', '').split(',')
        if telegram_token and channels:
            self.telegram_crawler = TelegramCrawler(telegram_token, channels)
    
    async def crawl_platforms(self, event: Dict) -> Dict:
        """Crawl all enabled platforms"""
        keywords = event.get('keywords', [])
        platforms = event.get('platforms', ['twitter', 'reddit', 'telegram'])
        max_results_per_platform = event.get('max_results_per_platform', 50)
        hours_back = event.get('hours_back', 24)
        
        results = {
            'timestamp': datetime.utcnow().isoformat(),
            'platforms': {},
            'total_posts': 0,
            'errors': []
        }
        
        # Crawl Twitter
        if 'twitter' in platforms and self.twitter_crawler:
            try:
                twitter_posts = await self.twitter_crawler.search_tweets(
                    keywords, max_results_per_platform, hours_back
                )
                results['platforms']['twitter'] = {
                    'posts_found': len(twitter_posts),
                    'posts': [asdict(post) for post in twitter_posts]
                }
                results['total_posts'] += len(twitter_posts)
            except Exception as e:
                logger.error(f"Twitter crawl failed: {e}")
                results['errors'].append(f"Twitter: {str(e)}")
        
        # Crawl Reddit
        if 'reddit' in platforms and self.reddit_crawler:
            try:
                reddit_posts = await self.reddit_crawler.search_posts(
                    keywords, max_results_per_platform, hours_back
                )
                results['platforms']['reddit'] = {
                    'posts_found': len(reddit_posts),
                    'posts': [asdict(post) for post in reddit_posts]
                }
                results['total_posts'] += len(reddit_posts)
            except Exception as e:
                logger.error(f"Reddit crawl failed: {e}")
                results['errors'].append(f"Reddit: {str(e)}")
        
        # Crawl Telegram
        if 'telegram' in platforms and self.telegram_crawler:
            try:
                telegram_posts = await self.telegram_crawler.get_updates(
                    keywords, max_results_per_platform
                )
                results['platforms']['telegram'] = {
                    'posts_found': len(telegram_posts),
                    'posts': [asdict(post) for post in telegram_posts]
                }
                results['total_posts'] += len(telegram_posts)
            except Exception as e:
                logger.error(f"Telegram crawl failed: {e}")
                results['errors'].append(f"Telegram: {str(e)}")
        
        return results
    
    def save_to_s3(self, data: Dict, bucket: str, key: str):
        """Save crawl results to S3"""
        try:
            self.s3.put_object(
                Bucket=bucket,
                Key=key,
                Body=json.dumps(data, indent=2),
                ContentType='application/json'
            )
            logger.info(f"Saved results to s3://{bucket}/{key}")
        except Exception as e:
            logger.error(f"Failed to save to S3: {e}")
    
    def send_to_sqs(self, posts: List[Dict], queue_url: str):
        """Send posts to SQS for processing"""
        sqs = boto3.client('sqs')
        
        try:
            for post in posts:
                sqs.send_message(
                    QueueUrl=queue_url,
                    MessageBody=json.dumps(post),
                    MessageAttributes={
                        'platform': {'StringValue': post['platform'], 'DataType': 'String'},
                        'timestamp': {'StringValue': post['published_at'], 'DataType': 'String'}
                    }
                )
            logger.info(f"Sent {len(posts)} posts to SQS")
        except Exception as e:
            logger.error(f"Failed to send to SQS: {e}")

# Lambda entry points
crawler_instance = None

def lambda_handler(event, context):
    """AWS Lambda entry point"""
    global crawler_instance
    
    if not crawler_instance:
        crawler_instance = SocialCrawlerLambda()
    
    # Run async crawl
    loop = asyncio.new_event_loop()
    asyncio.set_event_loop(loop)
    
    try:
        results = loop.run_until_complete(crawler_instance.crawl_platforms(event))
        
        # Save results to S3 if configured
        s3_bucket = os.environ.get('S3_RESULTS_BUCKET')
        if s3_bucket:
            timestamp = datetime.utcnow().strftime('%Y/%m/%d/%H%M%S')
            s3_key = f"social-crawl-results/{timestamp}.json"
            crawler_instance.save_to_s3(results, s3_bucket, s3_key)
        
        # Send to SQS if configured
        sqs_queue_url = os.environ.get('SQS_QUEUE_URL')
        if sqs_queue_url:
            all_posts = []
            for platform_data in results['platforms'].values():
                all_posts.extend(platform_data['posts'])
            crawler_instance.send_to_sqs(all_posts, sqs_queue_url)
        
        return {
            'statusCode': 200,
            'body': json.dumps(results)
        }
        
    except Exception as e:
        logger.error(f"Lambda execution failed: {e}")
        return {
            'statusCode': 500,
            'body': json.dumps({'error': str(e)})
        }
    
    finally:
        loop.close()

def scheduled_crawl_handler(event, context):
    """Handler for scheduled crawls via EventBridge"""
    # Default keywords for crypto/blockchain monitoring
    default_keywords = [
        'bitcoin', 'ethereum', 'crypto', 'blockchain', 'defi', 'nft',
        'smart contract', 'vulnerability', 'hack', 'exploit', 'rug pull'
    ]
    
    scheduled_event = {
        'keywords': default_keywords,
        'platforms': ['twitter', 'reddit'],
        'max_results_per_platform': 100,
        'hours_back': 1  # For hourly runs
    }
    
    return lambda_handler(scheduled_event, context)

if __name__ == "__main__":
    # For local testing
    test_event = {
        'keywords': ['bitcoin', 'ethereum', 'defi'],
        'platforms': ['twitter', 'reddit'],
        'max_results_per_platform': 10,
        'hours_back': 24
    }
    
    result = lambda_handler(test_event, None)
    print(json.dumps(result, indent=2))