#!/usr/bin/env python3
"""
Social Media Crawler Lambda Function
Serverless crawler for Twitter/X, Reddit, and Telegram
"""

import json
import os
import time
import asyncio
import logging
from datetime import datetime, timedelta
from typing import Dict, List, Optional, Any
import concurrent.futures
import boto3
import psycopg2
from psycopg2.extras import RealDictCursor
import redis
import requests
from dataclasses import dataclass, asdict
import re
from urllib.parse import quote_plus

# Configure logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

@dataclass
class CrawlResult:
    platform: str
    posts_collected: int
    keyword_matches: int
    execution_time: float
    success: bool
    error: Optional[str] = None
    data: Optional[List[Dict]] = None

@dataclass
class SocialPost:
    platform: str
    platform_id: str
    author_username: str
    author_id: str
    content: str
    url: str
    published_at: str
    engagement_score: int
    metadata: Dict
    matched_keywords: List[str]

class KeywordMatcher:
    """Advanced keyword matching engine"""
    
    def __init__(self, rules: List[Dict]):
        self.rules = rules
        self.compiled_patterns = self._compile_patterns()
    
    def _compile_patterns(self) -> Dict:
        patterns = {}
        for rule in self.rules:
            if rule.get('is_active', False):
                keywords = rule.get('keywords', [])
                patterns[rule['id']] = {
                    'rule': rule,
                    'patterns': [re.compile(kw, re.IGNORECASE) for kw in keywords if isinstance(kw, str)]
                }
        return patterns
    
    def match_content(self, content: str, platform: str) -> List[Dict]:
        matches = []
        for rule_id, pattern_data in self.compiled_patterns.items():
            rule = pattern_data['rule']
            
            # Platform filter
            if platform not in rule.get('platforms', [platform]):
                continue
            
            # Check patterns
            for pattern in pattern_data['patterns']:
                if pattern.search(content):
                    matches.append({
                        'rule_id': rule_id,
                        'keyword': pattern.pattern,
                        'category': rule.get('category', 'general'),
                        'priority': rule.get('priority', 5),
                        'score': self._calculate_score(content, pattern.pattern)
                    })
        
        return matches
    
    def _calculate_score(self, content: str, keyword: str) -> float:
        # Simple scoring based on keyword frequency and content length
        count = content.lower().count(keyword.lower())
        length_factor = min(len(content) / 100, 5)
        return count * 2 + length_factor

class TwitterCrawler:
    """Enhanced Twitter API v2 crawler"""
    
    def __init__(self, bearer_token: str, keyword_matcher: KeywordMatcher):
        self.bearer_token = bearer_token
        self.keyword_matcher = keyword_matcher
        self.api_base = "https://api.twitter.com/2"
        self.headers = {"Authorization": f"Bearer {bearer_token}"}
    
    async def crawl(self, options: Dict) -> CrawlResult:
        start_time = time.time()
        posts = []
        total_matches = 0
        
        try:
            keywords = options.get('keywords', ['blockchain', 'cryptocurrency', 'defi'])
            
            for keyword in keywords[:5]:  # Limit to 5 keywords for Lambda
                query = self._build_query(keyword)
                tweets = await self._search_tweets(query)
                
                for tweet in tweets.get('data', []):
                    processed_post = self._process_tweet(tweet, tweets.get('includes', {}))
                    if processed_post:
                        posts.append(processed_post)
                        total_matches += len(processed_post.matched_keywords)
                
                # Rate limiting
                await asyncio.sleep(1)
            
            return CrawlResult(
                platform='twitter',
                posts_collected=len(posts),
                keyword_matches=total_matches,
                execution_time=time.time() - start_time,
                success=True,
                data=[asdict(post) for post in posts]
            )
            
        except Exception as e:
            logger.error(f"Twitter crawl failed: {str(e)}")
            return CrawlResult(
                platform='twitter',
                posts_collected=0,
                keyword_matches=0,
                execution_time=time.time() - start_time,
                success=False,
                error=str(e)
            )
    
    def _build_query(self, keyword: str) -> str:
        return f"{keyword} -is:retweet -is:reply lang:en"
    
    async def _search_tweets(self, query: str) -> Dict:
        params = {
            'query': query,
            'max_results': 50,  # Reduced for Lambda constraints
            'tweet.fields': 'created_at,author_id,public_metrics,lang',
            'user.fields': 'username,name,verified',
            'expansions': 'author_id'
        }
        
        response = requests.get(
            f"{self.api_base}/tweets/search/recent",
            headers=self.headers,
            params=params,
            timeout=30
        )
        
        if response.status_code == 200:
            return response.json()
        else:
            raise Exception(f"Twitter API error: {response.status_code} - {response.text}")
    
    def _process_tweet(self, tweet: Dict, includes: Dict) -> Optional[SocialPost]:
        try:
            # Get user info
            users = {user['id']: user for user in includes.get('users', [])}
            user = users.get(tweet['author_id'], {})
            
            content = tweet['text']
            matches = self.keyword_matcher.match_content(content, 'twitter')
            
            if not matches:
                return None
            
            metrics = tweet.get('public_metrics', {})
            engagement_score = (
                metrics.get('like_count', 0) +
                metrics.get('retweet_count', 0) * 3 +
                metrics.get('reply_count', 0) * 2
            )
            
            return SocialPost(
                platform='twitter',
                platform_id=tweet['id'],
                author_username=user.get('username', ''),
                author_id=tweet['author_id'],
                content=content,
                url=f"https://twitter.com/{user.get('username', 'unknown')}/status/{tweet['id']}",
                published_at=tweet['created_at'],
                engagement_score=engagement_score,
                metadata={
                    'metrics': metrics,
                    'user_verified': user.get('verified', False),
                    'language': tweet.get('lang', 'en')
                },
                matched_keywords=[match['keyword'] for match in matches]
            )
            
        except Exception as e:
            logger.error(f"Error processing tweet {tweet.get('id', 'unknown')}: {str(e)}")
            return None

class RedditCrawler:
    """Enhanced Reddit crawler with OAuth"""
    
    def __init__(self, client_id: str, client_secret: str, username: str, password: str, keyword_matcher: KeywordMatcher):
        self.client_id = client_id
        self.client_secret = client_secret
        self.username = username
        self.password = password
        self.keyword_matcher = keyword_matcher
        self.access_token = None
        self.user_agent = "SocialCrawlerLambda/1.0"
    
    async def crawl(self, options: Dict) -> CrawlResult:
        start_time = time.time()
        posts = []
        total_matches = 0
        
        try:
            if not await self._authenticate():
                raise Exception("Reddit authentication failed")
            
            subreddits = options.get('subreddits', ['cryptocurrency', 'ethereum', 'defi'])
            
            for subreddit in subreddits[:3]:  # Limit for Lambda
                subreddit_posts = await self._get_subreddit_posts(subreddit)
                
                for post_data in subreddit_posts:
                    processed_post = self._process_post(post_data, subreddit)
                    if processed_post:
                        posts.append(processed_post)
                        total_matches += len(processed_post.matched_keywords)
                
                # Rate limiting
                await asyncio.sleep(1)
            
            return CrawlResult(
                platform='reddit',
                posts_collected=len(posts),
                keyword_matches=total_matches,
                execution_time=time.time() - start_time,
                success=True,
                data=[asdict(post) for post in posts]
            )
            
        except Exception as e:
            logger.error(f"Reddit crawl failed: {str(e)}")
            return CrawlResult(
                platform='reddit',
                posts_collected=0,
                keyword_matches=0,
                execution_time=time.time() - start_time,
                success=False,
                error=str(e)
            )
    
    async def _authenticate(self) -> bool:
        try:
            auth = requests.auth.HTTPBasicAuth(self.client_id, self.client_secret)
            data = {
                'grant_type': 'password',
                'username': self.username,
                'password': self.password
            }
            headers = {'User-Agent': self.user_agent}
            
            response = requests.post(
                'https://www.reddit.com/api/v1/access_token',
                auth=auth,
                data=data,
                headers=headers,
                timeout=30
            )
            
            if response.status_code == 200:
                self.access_token = response.json()['access_token']
                return True
            else:
                logger.error(f"Reddit auth failed: {response.status_code}")
                return False
                
        except Exception as e:
            logger.error(f"Reddit authentication error: {str(e)}")
            return False
    
    async def _get_subreddit_posts(self, subreddit: str) -> List[Dict]:
        headers = {
            'Authorization': f'Bearer {self.access_token}',
            'User-Agent': self.user_agent
        }
        
        response = requests.get(
            f'https://oauth.reddit.com/r/{subreddit}/hot',
            headers=headers,
            params={'limit': 25, 'raw_json': 1},
            timeout=30
        )
        
        if response.status_code == 200:
            data = response.json()
            return [child['data'] for child in data.get('data', {}).get('children', [])]
        else:
            raise Exception(f"Reddit API error: {response.status_code}")
    
    def _process_post(self, post_data: Dict, subreddit: str) -> Optional[SocialPost]:
        try:
            # Skip removed/deleted posts
            if post_data.get('removed_by_category') or post_data.get('author') == '[deleted]':
                return None
            
            title = post_data.get('title', '')
            selftext = post_data.get('selftext', '')
            content = f"{title}\n\n{selftext}".strip()
            
            matches = self.keyword_matcher.match_content(content, 'reddit')
            
            if not matches:
                return None
            
            engagement_score = (
                max(0, post_data.get('score', 0)) +
                post_data.get('num_comments', 0) * 2 +
                post_data.get('total_awards_received', 0) * 5
            )
            
            return SocialPost(
                platform='reddit',
                platform_id=post_data['id'],
                author_username=post_data['author'],
                author_id=post_data.get('author_fullname', ''),
                content=content,
                url=f"https://reddit.com{post_data['permalink']}",
                published_at=datetime.fromtimestamp(post_data['created_utc']).isoformat(),
                engagement_score=engagement_score,
                metadata={
                    'subreddit': subreddit,
                    'score': post_data.get('score', 0),
                    'num_comments': post_data.get('num_comments', 0),
                    'awards': post_data.get('total_awards_received', 0),
                    'nsfw': post_data.get('over_18', False)
                },
                matched_keywords=[match['keyword'] for match in matches]
            )
            
        except Exception as e:
            logger.error(f"Error processing Reddit post {post_data.get('id', 'unknown')}: {str(e)}")
            return None

class TelegramCrawler:
    """Enhanced Telegram Bot API crawler"""
    
    def __init__(self, bot_token: str, keyword_matcher: KeywordMatcher):
        self.bot_token = bot_token
        self.keyword_matcher = keyword_matcher
        self.api_base = f"https://api.telegram.org/bot{bot_token}"
    
    async def crawl(self, options: Dict) -> CrawlResult:
        start_time = time.time()
        posts = []
        total_matches = 0
        
        try:
            # Get recent updates (would need webhook setup for production)
            updates = await self._get_updates()
            
            for update in updates:
                if 'channel_post' in update:
                    processed_post = self._process_message(update['channel_post'])
                    if processed_post:
                        posts.append(processed_post)
                        total_matches += len(processed_post.matched_keywords)
            
            return CrawlResult(
                platform='telegram',
                posts_collected=len(posts),
                keyword_matches=total_matches,
                execution_time=time.time() - start_time,
                success=True,
                data=[asdict(post) for post in posts]
            )
            
        except Exception as e:
            logger.error(f"Telegram crawl failed: {str(e)}")
            return CrawlResult(
                platform='telegram',
                posts_collected=0,
                keyword_matches=0,
                execution_time=time.time() - start_time,
                success=False,
                error=str(e)
            )
    
    async def _get_updates(self) -> List[Dict]:
        response = requests.get(
            f"{self.api_base}/getUpdates",
            params={'allowed_updates': ['channel_post'], 'limit': 50},
            timeout=30
        )
        
        if response.status_code == 200:
            data = response.json()
            return data.get('result', [])
        else:
            raise Exception(f"Telegram API error: {response.status_code}")
    
    def _process_message(self, message: Dict) -> Optional[SocialPost]:
        try:
            content = message.get('text', '') or message.get('caption', '')
            if not content:
                return None
            
            matches = self.keyword_matcher.match_content(content, 'telegram')
            
            if not matches:
                return None
            
            chat = message.get('chat', {})
            channel_username = chat.get('username', f"channel_{chat.get('id', 'unknown')}")
            
            return SocialPost(
                platform='telegram',
                platform_id=f"{channel_username}_{message['message_id']}",
                author_username=channel_username,
                author_id=str(chat.get('id', '')),
                content=content,
                url=f"https://t.me/{channel_username}/{message['message_id']}",
                published_at=datetime.fromtimestamp(message['date']).isoformat(),
                engagement_score=message.get('views', 0) // 10,  # Estimated engagement
                metadata={
                    'chat_title': chat.get('title', ''),
                    'message_id': message['message_id'],
                    'views': message.get('views', 0),
                    'has_media': any(key in message for key in ['photo', 'video', 'document'])
                },
                matched_keywords=[match['keyword'] for match in matches]
            )
            
        except Exception as e:
            logger.error(f"Error processing Telegram message: {str(e)}")
            return None

class DatabaseManager:
    """Manages database connections and operations"""
    
    def __init__(self, connection_string: str):
        self.connection_string = connection_string
    
    def store_results(self, results: List[CrawlResult]) -> Dict:
        """Store crawl results in database"""
        stored_count = 0
        
        try:
            with psycopg2.connect(self.connection_string) as conn:
                with conn.cursor(cursor_factory=RealDictCursor) as cursor:
                    
                    for result in results:
                        if result.success and result.data:
                            for post_dict in result.data:
                                try:
                                    # Insert social media post
                                    cursor.execute("""
                                        INSERT INTO social_media_posts (
                                            platform, platform_id, author_username, author_id,
                                            content, url, published_at, engagement_score,
                                            metadata, matched_keywords, created_at, updated_at
                                        ) VALUES (
                                            %(platform)s, %(platform_id)s, %(author_username)s, %(author_id)s,
                                            %(content)s, %(url)s, %(published_at)s, %(engagement_score)s,
                                            %(metadata)s, %(matched_keywords)s, NOW(), NOW()
                                        ) ON CONFLICT (platform_id) DO UPDATE SET
                                            engagement_score = EXCLUDED.engagement_score,
                                            metadata = EXCLUDED.metadata,
                                            updated_at = NOW()
                                    """, {
                                        **post_dict,
                                        'metadata': json.dumps(post_dict['metadata']),
                                        'matched_keywords': json.dumps(post_dict['matched_keywords'])
                                    })
                                    stored_count += 1
                                    
                                except Exception as e:
                                    logger.error(f"Error storing post: {str(e)}")
                    
                    conn.commit()
            
            return {'stored_count': stored_count, 'success': True}
            
        except Exception as e:
            logger.error(f"Database storage error: {str(e)}")
            return {'stored_count': 0, 'success': False, 'error': str(e)}

class CrawlerOrchestrator:
    """Main orchestrator for the Lambda crawler"""
    
    def __init__(self):
        self.keyword_matcher = None
        self.crawlers = {}
        self.db_manager = None
        self._initialize()
    
    def _initialize(self):
        """Initialize crawlers and components"""
        try:
            # Load configuration from environment
            config = self._load_config()
            
            # Initialize keyword matcher
            keyword_rules = self._load_keyword_rules()
            self.keyword_matcher = KeywordMatcher(keyword_rules)
            
            # Initialize database manager
            if config['database']['connection_string']:
                self.db_manager = DatabaseManager(config['database']['connection_string'])
            
            # Initialize platform crawlers
            if config['twitter']['enabled'] and config['twitter']['bearer_token']:
                self.crawlers['twitter'] = TwitterCrawler(
                    config['twitter']['bearer_token'], 
                    self.keyword_matcher
                )
            
            if config['reddit']['enabled'] and all([
                config['reddit']['client_id'],
                config['reddit']['client_secret'],
                config['reddit']['username'],
                config['reddit']['password']
            ]):
                self.crawlers['reddit'] = RedditCrawler(
                    config['reddit']['client_id'],
                    config['reddit']['client_secret'],
                    config['reddit']['username'],
                    config['reddit']['password'],
                    self.keyword_matcher
                )
            
            if config['telegram']['enabled'] and config['telegram']['bot_token']:
                self.crawlers['telegram'] = TelegramCrawler(
                    config['telegram']['bot_token'],
                    self.keyword_matcher
                )
            
            logger.info(f"Initialized crawlers for platforms: {list(self.crawlers.keys())}")
            
        except Exception as e:
            logger.error(f"Initialization failed: {str(e)}")
            raise
    
    def _load_config(self) -> Dict:
        """Load configuration from environment variables"""
        return {
            'twitter': {
                'enabled': os.getenv('TWITTER_ENABLED', 'true').lower() == 'true',
                'bearer_token': os.getenv('TWITTER_BEARER_TOKEN', '')
            },
            'reddit': {
                'enabled': os.getenv('REDDIT_ENABLED', 'true').lower() == 'true',
                'client_id': os.getenv('REDDIT_CLIENT_ID', ''),
                'client_secret': os.getenv('REDDIT_CLIENT_SECRET', ''),
                'username': os.getenv('REDDIT_USERNAME', ''),
                'password': os.getenv('REDDIT_PASSWORD', '')
            },
            'telegram': {
                'enabled': os.getenv('TELEGRAM_ENABLED', 'true').lower() == 'true',
                'bot_token': os.getenv('TELEGRAM_BOT_TOKEN', '')
            },
            'database': {
                'connection_string': os.getenv('DATABASE_URL', '')
            }
        }
    
    def _load_keyword_rules(self) -> List[Dict]:
        """Load keyword rules from database or default set"""
        try:
            if self.db_manager:
                # Would load from database in production
                pass
        except Exception as e:
            logger.warning(f"Could not load keyword rules from database: {str(e)}")
        
        # Default keyword rules for blockchain/crypto content
        return [
            {
                'id': 1,
                'name': 'Cryptocurrency',
                'keywords': ['bitcoin', 'ethereum', 'crypto', 'blockchain'],
                'platforms': ['twitter', 'reddit', 'telegram'],
                'category': 'cryptocurrency',
                'priority': 8,
                'is_active': True
            },
            {
                'id': 2,
                'name': 'DeFi',
                'keywords': ['defi', 'uniswap', 'aave', 'compound', 'makerdao'],
                'platforms': ['twitter', 'reddit', 'telegram'],
                'category': 'defi',
                'priority': 9,
                'is_active': True
            },
            {
                'id': 3,
                'name': 'Smart Contracts',
                'keywords': ['smart contract', 'solidity', 'vulnerability', 'exploit'],
                'platforms': ['twitter', 'reddit', 'telegram'],
                'category': 'security',
                'priority': 10,
                'is_active': True
            }
        ]
    
    async def execute_crawl(self, event: Dict) -> Dict:
        """Execute crawling job"""
        job_id = event.get('job_id', f"lambda_crawl_{int(time.time())}")
        platforms = event.get('platforms', list(self.crawlers.keys()))
        
        logger.info(f"Starting crawl job {job_id} for platforms: {platforms}")
        
        start_time = time.time()
        results = []
        
        # Execute crawlers concurrently
        async def crawl_platform(platform_name: str, crawler: Any, options: Dict) -> CrawlResult:
            try:
                return await crawler.crawl(options)
            except Exception as e:
                logger.error(f"Platform {platform_name} crawl failed: {str(e)}")
                return CrawlResult(
                    platform=platform_name,
                    posts_collected=0,
                    keyword_matches=0,
                    execution_time=0,
                    success=False,
                    error=str(e)
                )
        
        # Create tasks for concurrent execution
        tasks = []
        for platform in platforms:
            if platform in self.crawlers:
                crawler = self.crawlers[platform]
                options = event.get('platform_options', {}).get(platform, {})
                task = crawl_platform(platform, crawler, options)
                tasks.append(task)
        
        # Execute tasks concurrently
        if tasks:
            results = await asyncio.gather(*tasks, return_exceptions=True)
        
        # Filter out exceptions and convert to CrawlResult objects
        valid_results = []
        for result in results:
            if isinstance(result, CrawlResult):
                valid_results.append(result)
            elif isinstance(result, Exception):
                logger.error(f"Crawl task exception: {str(result)}")
        
        # Store results in database
        storage_result = {'stored_count': 0, 'success': False}
        if self.db_manager and valid_results:
            storage_result = self.db_manager.store_results(valid_results)
        
        # Compile final results
        total_posts = sum(r.posts_collected for r in valid_results)
        total_matches = sum(r.keyword_matches for r in valid_results)
        execution_time = time.time() - start_time
        
        response = {
            'job_id': job_id,
            'execution_time': execution_time,
            'platforms_processed': len(valid_results),
            'total_posts_collected': total_posts,
            'total_keyword_matches': total_matches,
            'posts_stored': storage_result['stored_count'],
            'storage_success': storage_result['success'],
            'platform_results': [
                {
                    'platform': r.platform,
                    'success': r.success,
                    'posts_collected': r.posts_collected,
                    'keyword_matches': r.keyword_matches,
                    'execution_time': r.execution_time,
                    'error': r.error
                }
                for r in valid_results
            ],
            'timestamp': datetime.utcnow().isoformat()
        }
        
        logger.info(f"Crawl job {job_id} completed: {total_posts} posts, {total_matches} matches")
        
        return response

# Lambda handler
def lambda_handler(event, context):
    """AWS Lambda entry point"""
    try:
        orchestrator = CrawlerOrchestrator()
        
        # Run the async crawl
        loop = asyncio.new_event_loop()
        asyncio.set_event_loop(loop)
        
        result = loop.run_until_complete(orchestrator.execute_crawl(event))
        
        return {
            'statusCode': 200,
            'headers': {
                'Content-Type': 'application/json',
                'Access-Control-Allow-Origin': '*'
            },
            'body': json.dumps(result)
        }
        
    except Exception as e:
        logger.error(f"Lambda execution failed: {str(e)}")
        
        return {
            'statusCode': 500,
            'headers': {
                'Content-Type': 'application/json',
                'Access-Control-Allow-Origin': '*'
            },
            'body': json.dumps({
                'error': str(e),
                'timestamp': datetime.utcnow().isoformat()
            })
        }

# For local testing
if __name__ == "__main__":
    # Test event
    test_event = {
        'job_id': 'test_crawl_001',
        'platforms': ['twitter'],
        'platform_options': {
            'twitter': {
                'keywords': ['blockchain', 'ethereum']
            }
        }
    }
    
    result = lambda_handler(test_event, None)
    print(json.dumps(result, indent=2))
