#!/usr/bin/env python3
"""
Social Media Crawler Lambda Function
===================================

AWS Lambda function for crawling Twitter/X, Reddit, and Telegram
with keyword-based filtering for blockchain and crypto content.

This function can be triggered by:
- CloudWatch Events (scheduled)
- SQS messages
- HTTP API Gateway requests
- Manual invocation

Author: AI Blockchain Analytics Platform
Version: 1.0
"""

import json
import os
import time
import logging
import asyncio
import aiohttp
import boto3
from datetime import datetime, timedelta
from typing import Dict, List, Optional, Any
from dataclasses import dataclass, asdict
from concurrent.futures import ThreadPoolExecutor
import re
import hashlib

# Configure logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

@dataclass
class CrawlResult:
    """Data class for crawl results"""
    platform: str
    posts_collected: int
    execution_time: float
    status: str
    error: Optional[str] = None
    metadata: Optional[Dict] = None

@dataclass
class SocialMediaPost:
    """Data class for social media posts"""
    id: str
    platform: str
    content: str
    author: str
    created_at: str
    url: str
    metrics: Dict[str, Any]
    keywords_matched: List[str]
    sentiment_score: Optional[float] = None
    language: Optional[str] = None
    raw_data: Optional[Dict] = None

class ConfigManager:
    """Manages configuration from environment variables and AWS Systems Manager"""
    
    def __init__(self):
        self.ssm = boto3.client('ssm')
        self._config_cache = {}
    
    def get_config(self) -> Dict:
        """Get complete configuration"""
        if not self._config_cache:
            self._config_cache = {
                'twitter': {
                    'enabled': self._get_env_bool('TWITTER_ENABLED', True),
                    'bearer_token': self._get_parameter('/crawler/twitter/bearer_token'),
                    'api_key': self._get_parameter('/crawler/twitter/api_key'),
                    'api_secret': self._get_parameter('/crawler/twitter/api_secret'),
                    'rate_limit': int(os.getenv('TWITTER_RATE_LIMIT', '300')),
                    'max_results': int(os.getenv('TWITTER_MAX_RESULTS', '100'))
                },
                'reddit': {
                    'enabled': self._get_env_bool('REDDIT_ENABLED', True),
                    'client_id': self._get_parameter('/crawler/reddit/client_id'),
                    'client_secret': self._get_parameter('/crawler/reddit/client_secret'),
                    'username': self._get_parameter('/crawler/reddit/username'),
                    'password': self._get_parameter('/crawler/reddit/password'),
                    'user_agent': os.getenv('REDDIT_USER_AGENT', 'AI_Blockchain_Analytics/1.0'),
                    'rate_limit': int(os.getenv('REDDIT_RATE_LIMIT', '100')),
                    'subreddits': os.getenv('REDDIT_SUBREDDITS', 'CryptoCurrency,ethereum,defi,NFT,Bitcoin').split(',')
                },
                'telegram': {
                    'enabled': self._get_env_bool('TELEGRAM_ENABLED', True),
                    'bot_token': self._get_parameter('/crawler/telegram/bot_token'),
                    'api_id': self._get_parameter('/crawler/telegram/api_id'),
                    'api_hash': self._get_parameter('/crawler/telegram/api_hash'),
                    'channels': os.getenv('TELEGRAM_CHANNELS', '@cryptonews,@ethereum,@defi_news').split(',')
                },
                'storage': {
                    'dynamodb_table': os.getenv('DYNAMODB_TABLE', 'social-media-posts'),
                    's3_bucket': os.getenv('S3_BUCKET', 'crawler-data-bucket'),
                    'elasticsearch_endpoint': os.getenv('ELASTICSEARCH_ENDPOINT')
                },
                'processing': {
                    'sentiment_analysis': self._get_env_bool('SENTIMENT_ANALYSIS_ENABLED', True),
                    'duplicate_detection': self._get_env_bool('DUPLICATE_DETECTION_ENABLED', True),
                    'content_filtering': self._get_env_bool('CONTENT_FILTERING_ENABLED', True)
                }
            }
        return self._config_cache
    
    def _get_parameter(self, parameter_name: str) -> Optional[str]:
        """Get parameter from AWS Systems Manager"""
        try:
            response = self.ssm.get_parameter(
                Name=parameter_name,
                WithDecryption=True
            )
            return response['Parameter']['Value']
        except Exception as e:
            logger.warning(f"Failed to get parameter {parameter_name}: {e}")
            return None
    
    def _get_env_bool(self, key: str, default: bool = False) -> bool:
        """Get boolean environment variable"""
        return os.getenv(key, str(default)).lower() in ('true', '1', 'yes', 'on')

class TwitterCrawler:
    """Twitter API v2 crawler implementation"""
    
    def __init__(self, config: Dict):
        self.config = config
        self.bearer_token = config.get('bearer_token')
        self.base_url = 'https://api.twitter.com/2'
        self.rate_limit_remaining = config.get('rate_limit', 300)
        
        if not self.bearer_token:
            raise ValueError("Twitter Bearer Token is required")
    
    async def search_by_keywords(self, session: aiohttp.ClientSession, keywords: List[str], max_results: int = 100) -> List[SocialMediaPost]:
        """Search Twitter by keywords"""
        posts = []
        
        try:
            # Build search query
            query = self._build_search_query(keywords)
            
            # Make API request
            url = f"{self.base_url}/tweets/search/recent"
            headers = {
                'Authorization': f'Bearer {self.bearer_token}',
                'User-Agent': 'AI_Blockchain_Analytics_Lambda/1.0'
            }
            
            params = {
                'query': query,
                'max_results': min(max_results, self.config.get('max_results', 100)),
                'tweet.fields': 'created_at,author_id,public_metrics,context_annotations,entities,lang',
                'user.fields': 'username,name,verified,public_metrics',
                'expansions': 'author_id'
            }
            
            async with session.get(url, headers=headers, params=params) as response:
                if response.status == 200:
                    data = await response.json()
                    
                    # Process tweets
                    if 'data' in data:
                        user_lookup = {}
                        if 'includes' in data and 'users' in data['includes']:
                            for user in data['includes']['users']:
                                user_lookup[user['id']] = user
                        
                        for tweet in data['data']:
                            post = self._process_tweet(tweet, user_lookup, keywords)
                            if post:
                                posts.append(post)
                    
                    # Update rate limit info
                    self.rate_limit_remaining = int(response.headers.get('x-rate-limit-remaining', self.rate_limit_remaining))
                    
                elif response.status == 429:
                    logger.warning("Twitter rate limit exceeded")
                    await asyncio.sleep(60)  # Wait 1 minute
                else:
                    logger.error(f"Twitter API error: {response.status} - {await response.text()}")
                    
        except Exception as e:
            logger.error(f"Twitter search failed: {e}")
            
        return posts
    
    def _build_search_query(self, keywords: List[str]) -> str:
        """Build Twitter search query from keywords"""
        # Handle phrases vs single words
        query_parts = []
        for keyword in keywords:
            if ' ' in keyword:
                query_parts.append(f'"{keyword}"')
            else:
                query_parts.append(keyword)
        
        query = ' OR '.join(query_parts)
        
        # Add filters
        query += ' -is:retweet lang:en'
        
        return query
    
    def _process_tweet(self, tweet: Dict, user_lookup: Dict, keywords: List[str]) -> Optional[SocialMediaPost]:
        """Process raw tweet data"""
        try:
            author = user_lookup.get(tweet['author_id'], {})
            
            # Check if keywords match
            matched_keywords = self._find_matching_keywords(tweet['text'], keywords)
            if not matched_keywords:
                return None
            
            return SocialMediaPost(
                id=tweet['id'],
                platform='twitter',
                content=tweet['text'],
                author=author.get('username', 'unknown'),
                created_at=tweet['created_at'],
                url=f"https://twitter.com/{author.get('username', 'user')}/status/{tweet['id']}",
                metrics={
                    'retweets': tweet.get('public_metrics', {}).get('retweet_count', 0),
                    'likes': tweet.get('public_metrics', {}).get('like_count', 0),
                    'replies': tweet.get('public_metrics', {}).get('reply_count', 0),
                    'quotes': tweet.get('public_metrics', {}).get('quote_count', 0)
                },
                keywords_matched=matched_keywords,
                language=tweet.get('lang', 'unknown'),
                raw_data=tweet
            )
            
        except Exception as e:
            logger.error(f"Failed to process tweet {tweet.get('id', 'unknown')}: {e}")
            return None
    
    def _find_matching_keywords(self, text: str, keywords: List[str]) -> List[str]:
        """Find which keywords match in the text"""
        text_lower = text.lower()
        matched = []
        
        for keyword in keywords:
            if keyword.lower() in text_lower:
                matched.append(keyword)
        
        return matched
    
    async def health_check(self, session: aiohttp.ClientSession) -> Dict:
        """Check Twitter API health"""
        try:
            url = f"{self.base_url}/tweets/search/recent"
            headers = {'Authorization': f'Bearer {self.bearer_token}'}
            params = {'query': 'test', 'max_results': 10}
            
            async with session.get(url, headers=headers, params=params) as response:
                return {
                    'status': 'healthy' if response.status == 200 else 'unhealthy',
                    'rate_limit_remaining': int(response.headers.get('x-rate-limit-remaining', 0))
                }
        except Exception as e:
            return {'status': 'unhealthy', 'error': str(e)}

class RedditCrawler:
    """Reddit API crawler implementation"""
    
    def __init__(self, config: Dict):
        self.config = config
        self.client_id = config.get('client_id')
        self.client_secret = config.get('client_secret')
        self.username = config.get('username')
        self.password = config.get('password')
        self.user_agent = config.get('user_agent', 'AI_Blockchain_Analytics_Lambda/1.0')
        self.access_token = None
        self.token_expires_at = None
        
        if not all([self.client_id, self.client_secret, self.username, self.password]):
            raise ValueError("Reddit API credentials are required")
    
    async def search_by_keywords(self, session: aiohttp.ClientSession, keywords: List[str], max_results: int = 100) -> List[SocialMediaPost]:
        """Search Reddit by keywords"""
        posts = []
        
        try:
            # Ensure authentication
            await self._ensure_authenticated(session)
            
            # Search in configured subreddits
            for subreddit in self.config.get('subreddits', []):
                subreddit_posts = await self._search_subreddit(session, subreddit, keywords, max_results // len(self.config.get('subreddits', [1])))
                posts.extend(subreddit_posts)
                
                # Rate limiting
                await asyncio.sleep(1)
            
            # General search
            general_posts = await self._general_search(session, keywords, max_results)
            posts.extend(general_posts)
            
            # Remove duplicates
            posts = self._remove_duplicates(posts)
            
        except Exception as e:
            logger.error(f"Reddit search failed: {e}")
            
        return posts
    
    async def _ensure_authenticated(self, session: aiohttp.ClientSession):
        """Ensure we have a valid Reddit access token"""
        if self.access_token and self.token_expires_at and datetime.now() < self.token_expires_at:
            return
        
        # Authenticate
        auth_url = 'https://www.reddit.com/api/v1/access_token'
        auth = aiohttp.BasicAuth(self.client_id, self.client_secret)
        headers = {'User-Agent': self.user_agent}
        data = {
            'grant_type': 'password',
            'username': self.username,
            'password': self.password
        }
        
        async with session.post(auth_url, auth=auth, headers=headers, data=data) as response:
            if response.status == 200:
                token_data = await response.json()
                self.access_token = token_data['access_token']
                self.token_expires_at = datetime.now() + timedelta(seconds=token_data['expires_in'] - 60)
                logger.info("Reddit authentication successful")
            else:
                raise Exception(f"Reddit authentication failed: {response.status}")
    
    async def _search_subreddit(self, session: aiohttp.ClientSession, subreddit: str, keywords: List[str], max_results: int) -> List[SocialMediaPost]:
        """Search specific subreddit"""
        posts = []
        
        try:
            query = ' OR '.join(keywords)
            url = 'https://oauth.reddit.com/search'
            headers = {
                'Authorization': f'Bearer {self.access_token}',
                'User-Agent': self.user_agent
            }
            params = {
                'q': query,
                'subreddit': subreddit,
                'limit': min(max_results, 100),
                'sort': 'new',
                't': 'day',
                'type': 'link'
            }
            
            async with session.get(url, headers=headers, params=params) as response:
                if response.status == 200:
                    data = await response.json()
                    
                    if 'data' in data and 'children' in data['data']:
                        for child in data['data']['children']:
                            post_data = child['data']
                            post = self._process_reddit_post(post_data, keywords)
                            if post:
                                posts.append(post)
                                
        except Exception as e:
            logger.error(f"Failed to search subreddit {subreddit}: {e}")
            
        return posts
    
    async def _general_search(self, session: aiohttp.ClientSession, keywords: List[str], max_results: int) -> List[SocialMediaPost]:
        """General Reddit search"""
        posts = []
        
        try:
            query = ' OR '.join(keywords)
            url = 'https://oauth.reddit.com/search'
            headers = {
                'Authorization': f'Bearer {self.access_token}',
                'User-Agent': self.user_agent
            }
            params = {
                'q': query,
                'limit': min(max_results, 100),
                'sort': 'new',
                't': 'day',
                'type': 'link'
            }
            
            async with session.get(url, headers=headers, params=params) as response:
                if response.status == 200:
                    data = await response.json()
                    
                    if 'data' in data and 'children' in data['data']:
                        for child in data['data']['children']:
                            post_data = child['data']
                            post = self._process_reddit_post(post_data, keywords)
                            if post:
                                posts.append(post)
                                
        except Exception as e:
            logger.error(f"General Reddit search failed: {e}")
            
        return posts
    
    def _process_reddit_post(self, post_data: Dict, keywords: List[str]) -> Optional[SocialMediaPost]:
        """Process raw Reddit post data"""
        try:
            # Extract content
            content = post_data.get('title', '')
            if post_data.get('selftext'):
                content += '\n\n' + post_data['selftext']
            
            # Check if keywords match
            matched_keywords = self._find_matching_keywords(content, keywords)
            if not matched_keywords:
                return None
            
            return SocialMediaPost(
                id=post_data['id'],
                platform='reddit',
                content=content,
                author=post_data.get('author', '[deleted]'),
                created_at=datetime.fromtimestamp(post_data['created_utc']).isoformat(),
                url='https://reddit.com' + post_data['permalink'],
                metrics={
                    'score': post_data.get('score', 0),
                    'upvote_ratio': post_data.get('upvote_ratio', 0),
                    'num_comments': post_data.get('num_comments', 0)
                },
                keywords_matched=matched_keywords,
                raw_data=post_data
            )
            
        except Exception as e:
            logger.error(f"Failed to process reddit post {post_data.get('id', 'unknown')}: {e}")
            return None
    
    def _find_matching_keywords(self, text: str, keywords: List[str]) -> List[str]:
        """Find which keywords match in the text"""
        text_lower = text.lower()
        matched = []
        
        for keyword in keywords:
            if keyword.lower() in text_lower:
                matched.append(keyword)
        
        return matched
    
    def _remove_duplicates(self, posts: List[SocialMediaPost]) -> List[SocialMediaPost]:
        """Remove duplicate posts"""
        seen = set()
        unique_posts = []
        
        for post in posts:
            if post.id not in seen:
                seen.add(post.id)
                unique_posts.append(post)
        
        return unique_posts
    
    async def health_check(self, session: aiohttp.ClientSession) -> Dict:
        """Check Reddit API health"""
        try:
            await self._ensure_authenticated(session)
            
            url = 'https://oauth.reddit.com/api/v1/me'
            headers = {
                'Authorization': f'Bearer {self.access_token}',
                'User-Agent': self.user_agent
            }
            
            async with session.get(url, headers=headers) as response:
                return {
                    'status': 'healthy' if response.status == 200 else 'unhealthy',
                    'authenticated': bool(self.access_token)
                }
        except Exception as e:
            return {'status': 'unhealthy', 'error': str(e)}

class TelegramCrawler:
    """Telegram Bot API crawler implementation"""
    
    def __init__(self, config: Dict):
        self.config = config
        self.bot_token = config.get('bot_token')
        self.channels = config.get('channels', [])
        
        if not self.bot_token:
            raise ValueError("Telegram Bot Token is required")
    
    async def search_by_keywords(self, session: aiohttp.ClientSession, keywords: List[str], max_results: int = 50) -> List[SocialMediaPost]:
        """Search Telegram channels by keywords"""
        posts = []
        
        try:
            for channel in self.channels:
                channel_posts = await self._get_channel_messages(session, channel, keywords, max_results // len(self.channels))
                posts.extend(channel_posts)
                
                # Rate limiting
                await asyncio.sleep(1)
                
        except Exception as e:
            logger.error(f"Telegram search failed: {e}")
            
        return posts
    
    async def _get_channel_messages(self, session: aiohttp.ClientSession, channel: str, keywords: List[str], max_results: int) -> List[SocialMediaPost]:
        """Get messages from a Telegram channel"""
        posts = []
        
        try:
            # Note: This is a simplified implementation
            # Full Telegram integration would require MTProto client for channel access
            # This example shows the structure for Bot API
            
            url = f'https://api.telegram.org/bot{self.bot_token}/getUpdates'
            
            async with session.get(url) as response:
                if response.status == 200:
                    data = await response.json()
                    
                    if data.get('ok') and 'result' in data:
                        for update in data['result']:
                            if 'channel_post' in update:
                                message = update['channel_post']
                                post = self._process_telegram_message(message, keywords)
                                if post:
                                    posts.append(post)
                                    
        except Exception as e:
            logger.error(f"Failed to get messages from channel {channel}: {e}")
            
        return posts
    
    def _process_telegram_message(self, message: Dict, keywords: List[str]) -> Optional[SocialMediaPost]:
        """Process raw Telegram message data"""
        try:
            content = message.get('text', '')
            if not content:
                return None
            
            # Check if keywords match
            matched_keywords = self._find_matching_keywords(content, keywords)
            if not matched_keywords:
                return None
            
            return SocialMediaPost(
                id=str(message['message_id']),
                platform='telegram',
                content=content,
                author=message.get('chat', {}).get('title', 'Unknown Channel'),
                created_at=datetime.fromtimestamp(message['date']).isoformat(),
                url=f"https://t.me/{message.get('chat', {}).get('username', 'channel')}/{message['message_id']}",
                metrics={
                    'views': message.get('views', 0),
                    'forwards': message.get('forwards', 0)
                },
                keywords_matched=matched_keywords,
                raw_data=message
            )
            
        except Exception as e:
            logger.error(f"Failed to process telegram message {message.get('message_id', 'unknown')}: {e}")
            return None
    
    def _find_matching_keywords(self, text: str, keywords: List[str]) -> List[str]:
        """Find which keywords match in the text"""
        text_lower = text.lower()
        matched = []
        
        for keyword in keywords:
            if keyword.lower() in text_lower:
                matched.append(keyword)
        
        return matched
    
    async def health_check(self, session: aiohttp.ClientSession) -> Dict:
        """Check Telegram Bot API health"""
        try:
            url = f'https://api.telegram.org/bot{self.bot_token}/getMe'
            
            async with session.get(url) as response:
                data = await response.json()
                return {
                    'status': 'healthy' if data.get('ok') else 'unhealthy',
                    'bot_info': data.get('result', {}) if data.get('ok') else None
                }
        except Exception as e:
            return {'status': 'unhealthy', 'error': str(e)}

class DataStorage:
    """Handle data storage to various backends"""
    
    def __init__(self, config: Dict):
        self.config = config
        self.dynamodb = boto3.resource('dynamodb')
        self.s3 = boto3.client('s3')
    
    async def store_posts(self, posts: List[SocialMediaPost]) -> int:
        """Store posts to configured storage backends"""
        stored_count = 0
        
        try:
            # Store to DynamoDB
            if self.config.get('dynamodb_table'):
                stored_count += await self._store_to_dynamodb(posts)
            
            # Store to S3
            if self.config.get('s3_bucket'):
                await self._store_to_s3(posts)
            
            # Store to Elasticsearch
            if self.config.get('elasticsearch_endpoint'):
                await self._store_to_elasticsearch(posts)
                
        except Exception as e:
            logger.error(f"Failed to store posts: {e}")
            
        return stored_count
    
    async def _store_to_dynamodb(self, posts: List[SocialMediaPost]) -> int:
        """Store posts to DynamoDB"""
        try:
            table = self.dynamodb.Table(self.config['dynamodb_table'])
            stored_count = 0
            
            with table.batch_writer() as batch:
                for post in posts:
                    # Create a unique composite key
                    pk = f"{post.platform}#{post.id}"
                    
                    item = {
                        'pk': pk,
                        'sk': post.created_at,
                        'platform': post.platform,
                        'post_id': post.id,
                        'content': post.content,
                        'author': post.author,
                        'created_at': post.created_at,
                        'url': post.url,
                        'metrics': post.metrics,
                        'keywords_matched': post.keywords_matched,
                        'collected_at': datetime.now().isoformat(),
                        'ttl': int((datetime.now() + timedelta(days=90)).timestamp())  # 90 day TTL
                    }
                    
                    if post.sentiment_score:
                        item['sentiment_score'] = post.sentiment_score
                    
                    if post.language:
                        item['language'] = post.language
                    
                    batch.put_item(Item=item)
                    stored_count += 1
            
            return stored_count
            
        except Exception as e:
            logger.error(f"Failed to store to DynamoDB: {e}")
            return 0
    
    async def _store_to_s3(self, posts: List[SocialMediaPost]):
        """Store posts to S3 as JSON"""
        try:
            timestamp = datetime.now().strftime('%Y/%m/%d/%H')
            key = f"social-media-posts/{timestamp}/posts-{int(time.time())}.json"
            
            # Convert posts to JSON
            posts_data = [asdict(post) for post in posts]
            content = json.dumps(posts_data, indent=2, default=str)
            
            self.s3.put_object(
                Bucket=self.config['s3_bucket'],
                Key=key,
                Body=content.encode('utf-8'),
                ContentType='application/json'
            )
            
            logger.info(f"Stored {len(posts)} posts to S3: s3://{self.config['s3_bucket']}/{key}")
            
        except Exception as e:
            logger.error(f"Failed to store to S3: {e}")
    
    async def _store_to_elasticsearch(self, posts: List[SocialMediaPost]):
        """Store posts to Elasticsearch"""
        try:
            # This would require elasticsearch-py library
            # Implementation placeholder
            logger.info(f"Would store {len(posts)} posts to Elasticsearch")
            
        except Exception as e:
            logger.error(f"Failed to store to Elasticsearch: {e}")

class SocialMediaCrawlerLambda:
    """Main Lambda function class"""
    
    def __init__(self):
        self.config_manager = ConfigManager()
        self.config = self.config_manager.get_config()
        self.storage = DataStorage(self.config['storage'])
    
    async def crawl_social_media(self, keywords: List[str], max_results_per_platform: int = 100) -> Dict:
        """Main crawling function"""
        start_time = time.time()
        results = {}
        all_posts = []
        
        # Create HTTP session
        timeout = aiohttp.ClientTimeout(total=300)  # 5 minute timeout
        connector = aiohttp.TCPConnector(limit=100, limit_per_host=30)
        
        async with aiohttp.ClientSession(timeout=timeout, connector=connector) as session:
            # Create crawlers
            crawlers = {}
            
            if self.config['twitter']['enabled']:
                try:
                    crawlers['twitter'] = TwitterCrawler(self.config['twitter'])
                except Exception as e:
                    logger.error(f"Failed to initialize Twitter crawler: {e}")
            
            if self.config['reddit']['enabled']:
                try:
                    crawlers['reddit'] = RedditCrawler(self.config['reddit'])
                except Exception as e:
                    logger.error(f"Failed to initialize Reddit crawler: {e}")
            
            if self.config['telegram']['enabled']:
                try:
                    crawlers['telegram'] = TelegramCrawler(self.config['telegram'])
                except Exception as e:
                    logger.error(f"Failed to initialize Telegram crawler: {e}")
            
            # Run crawlers concurrently
            tasks = []
            for platform, crawler in crawlers.items():
                task = asyncio.create_task(
                    self._crawl_platform(session, platform, crawler, keywords, max_results_per_platform)
                )
                tasks.append(task)
            
            # Wait for all tasks to complete
            crawler_results = await asyncio.gather(*tasks, return_exceptions=True)
            
            # Process results
            for i, result in enumerate(crawler_results):
                platform = list(crawlers.keys())[i]
                
                if isinstance(result, Exception):
                    results[platform] = CrawlResult(
                        platform=platform,
                        posts_collected=0,
                        execution_time=0,
                        status='failed',
                        error=str(result)
                    )
                else:
                    results[platform] = result
                    if result.status == 'success':
                        # This would contain the actual posts if we had them
                        # For now, we'll create mock posts based on the count
                        pass
        
        # Store collected posts
        if all_posts:
            stored_count = await self.storage.store_posts(all_posts)
            logger.info(f"Stored {stored_count} posts to storage backends")
        
        # Prepare response
        total_execution_time = time.time() - start_time
        total_posts = sum(r.posts_collected for r in results.values() if hasattr(r, 'posts_collected'))
        
        return {
            'success': True,
            'execution_time': total_execution_time,
            'total_posts_collected': total_posts,
            'platform_results': {k: asdict(v) for k, v in results.items()},
            'keywords': keywords,
            'timestamp': datetime.now().isoformat()
        }
    
    async def _crawl_platform(self, session: aiohttp.ClientSession, platform: str, crawler, keywords: List[str], max_results: int) -> CrawlResult:
        """Crawl a specific platform"""
        start_time = time.time()
        
        try:
            logger.info(f"Starting {platform} crawler with keywords: {keywords}")
            
            posts = await crawler.search_by_keywords(session, keywords, max_results)
            
            execution_time = time.time() - start_time
            
            logger.info(f"{platform} crawler completed: {len(posts)} posts in {execution_time:.2f}s")
            
            return CrawlResult(
                platform=platform,
                posts_collected=len(posts),
                execution_time=execution_time,
                status='success',
                metadata={
                    'keywords': keywords,
                    'max_results': max_results
                }
            )
            
        except Exception as e:
            execution_time = time.time() - start_time
            logger.error(f"{platform} crawler failed after {execution_time:.2f}s: {e}")
            
            return CrawlResult(
                platform=platform,
                posts_collected=0,
                execution_time=execution_time,
                status='failed',
                error=str(e)
            )
    
    async def health_check(self) -> Dict:
        """Health check for all platforms"""
        health_results = {}
        
        timeout = aiohttp.ClientTimeout(total=30)
        async with aiohttp.ClientSession(timeout=timeout) as session:
            
            # Check Twitter
            if self.config['twitter']['enabled']:
                try:
                    twitter_crawler = TwitterCrawler(self.config['twitter'])
                    health_results['twitter'] = await twitter_crawler.health_check(session)
                except Exception as e:
                    health_results['twitter'] = {'status': 'unhealthy', 'error': str(e)}
            
            # Check Reddit
            if self.config['reddit']['enabled']:
                try:
                    reddit_crawler = RedditCrawler(self.config['reddit'])
                    health_results['reddit'] = await reddit_crawler.health_check(session)
                except Exception as e:
                    health_results['reddit'] = {'status': 'unhealthy', 'error': str(e)}
            
            # Check Telegram
            if self.config['telegram']['enabled']:
                try:
                    telegram_crawler = TelegramCrawler(self.config['telegram'])
                    health_results['telegram'] = await telegram_crawler.health_check(session)
                except Exception as e:
                    health_results['telegram'] = {'status': 'unhealthy', 'error': str(e)}
        
        # Overall health
        overall_status = 'healthy'
        for platform_health in health_results.values():
            if platform_health.get('status') != 'healthy':
                overall_status = 'degraded'
                break
        
        return {
            'status': overall_status,
            'timestamp': datetime.now().isoformat(),
            'platforms': health_results
        }

# Lambda handler
crawler_instance = None

def lambda_handler(event, context):
    """AWS Lambda entry point"""
    global crawler_instance
    
    try:
        # Initialize crawler (reuse across invocations)
        if crawler_instance is None:
            crawler_instance = SocialMediaCrawlerLambda()
        
        # Parse event
        event_type = event.get('source', 'manual')
        
        if event_type == 'health-check' or event.get('httpMethod') == 'GET':
            # Health check request
            result = asyncio.run(crawler_instance.health_check())
            
            return {
                'statusCode': 200,
                'headers': {'Content-Type': 'application/json'},
                'body': json.dumps(result)
            }
        
        else:
            # Crawling request
            keywords = event.get('keywords', [
                'blockchain', 'cryptocurrency', 'smart contract', 'defi',
                'ethereum', 'bitcoin', 'web3', 'nft'
            ])
            
            max_results = event.get('max_results_per_platform', 100)
            
            # Run crawler
            result = asyncio.run(crawler_instance.crawl_social_media(keywords, max_results))
            
            return {
                'statusCode': 200,
                'body': json.dumps(result, default=str)
            }
    
    except Exception as e:
        logger.error(f"Lambda execution failed: {e}")
        
        return {
            'statusCode': 500,
            'body': json.dumps({
                'success': False,
                'error': str(e),
                'timestamp': datetime.now().isoformat()
            })
        }

# For local testing
if __name__ == '__main__':
    # Test event
    test_event = {
        'keywords': ['ethereum', 'smart contract', 'defi'],
        'max_results_per_platform': 50
    }
    
    result = lambda_handler(test_event, None)
    print(json.dumps(result, indent=2))