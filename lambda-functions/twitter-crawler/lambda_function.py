#!/usr/bin/env python3
"""
Twitter Crawler Lambda Function

AWS Lambda function for serverless Twitter crawling with keyword rules.
Supports both scheduled crawling and real-time event processing.
"""

import json
import boto3
import requests
import os
import logging
from datetime import datetime, timezone
from typing import Dict, List, Any, Optional
import psycopg2
from psycopg2.extras import RealDictCursor

# Configure logging
logger = logging.getLogger()
logger.setLevel(logging.INFO)

class TwitterCrawlerLambda:
    def __init__(self):
        """Initialize the Twitter crawler with credentials and configuration."""
        self.bearer_token = os.environ.get('TWITTER_BEARER_TOKEN')
        self.db_host = os.environ.get('DB_HOST')
        self.db_name = os.environ.get('DB_NAME')
        self.db_user = os.environ.get('DB_USER')
        self.db_password = os.environ.get('DB_PASSWORD')
        self.base_url = "https://api.twitter.com/2"
        
        if not self.bearer_token:
            raise ValueError("TWITTER_BEARER_TOKEN environment variable required")
            
        self.headers = {
            'Authorization': f'Bearer {self.bearer_token}',
            'Content-Type': 'application/json'
        }

    def lambda_handler(self, event: Dict[str, Any], context) -> Dict[str, Any]:
        """
        Main Lambda handler function.
        
        Event can contain:
        - rule_id: Specific crawler rule to execute
        - rules: List of rule IDs to execute
        - mode: 'batch' or 'realtime'
        """
        try:
            logger.info(f"Starting Twitter crawler Lambda with event: {event}")
            
            mode = event.get('mode', 'batch')
            rule_id = event.get('rule_id')
            rule_ids = event.get('rules', [])
            
            if rule_id:
                rule_ids = [rule_id]
            
            if mode == 'batch':
                results = self.process_batch_crawl(rule_ids)
            else:
                results = self.process_realtime_crawl(event)
                
            logger.info(f"Crawler completed with results: {results}")
            
            return {
                'statusCode': 200,
                'body': json.dumps(results),
                'headers': {'Content-Type': 'application/json'}
            }
            
        except Exception as e:
            logger.error(f"Lambda execution failed: {str(e)}")
            return {
                'statusCode': 500,
                'body': json.dumps({
                    'error': str(e),
                    'timestamp': datetime.now(timezone.utc).isoformat()
                })
            }

    def process_batch_crawl(self, rule_ids: List[int] = None) -> Dict[str, Any]:
        """Process batch crawling for specified rules."""
        results = {
            'mode': 'batch',
            'rules_processed': 0,
            'total_posts_found': 0,
            'total_posts_stored': 0,
            'errors': [],
            'execution_time': 0
        }
        
        start_time = datetime.now()
        
        try:
            # Get active crawler rules
            rules = self.get_active_crawler_rules(rule_ids)
            
            for rule in rules:
                try:
                    rule_results = self.crawl_rule(rule)
                    results['rules_processed'] += 1
                    results['total_posts_found'] += rule_results.get('posts_found', 0)
                    results['total_posts_stored'] += rule_results.get('posts_stored', 0)
                    
                except Exception as e:
                    error_msg = f"Failed to process rule {rule['id']}: {str(e)}"
                    results['errors'].append(error_msg)
                    logger.error(error_msg)
            
        except Exception as e:
            results['errors'].append(f"Batch processing failed: {str(e)}")
            
        results['execution_time'] = (datetime.now() - start_time).total_seconds()
        return results

    def process_realtime_crawl(self, event: Dict[str, Any]) -> Dict[str, Any]:
        """Process real-time crawling event."""
        # This would handle webhook events or streaming data
        return {
            'mode': 'realtime',
            'message': 'Real-time processing not implemented in this version',
            'event': event
        }

    def crawl_rule(self, rule: Dict[str, Any]) -> Dict[str, Any]:
        """Crawl Twitter based on a specific rule."""
        rule_results = {
            'rule_id': rule['id'],
            'posts_found': 0,
            'posts_processed': 0,
            'posts_stored': 0,
            'errors': []
        }
        
        try:
            # Build search query
            query = self.build_search_query(rule)
            if not query:
                rule_results['errors'].append('No valid search query could be built')
                return rule_results
            
            # Get platform config
            config = rule.get('platform_configs', {}).get('twitter', {})
            max_results = min(config.get('max_results', 100), 100)  # API limit
            
            # Search tweets
            tweets_data = self.search_tweets(query, max_results, config)
            rule_results['posts_found'] = len(tweets_data.get('data', []))
            
            # Process and store tweets
            if tweets_data.get('data'):
                processed = self.process_tweets(tweets_data['data'], rule, tweets_data.get('includes', {}))
                rule_results['posts_processed'] = processed['processed']
                rule_results['posts_stored'] = processed['stored']
                rule_results['errors'].extend(processed['errors'])
            
            # Update rule statistics
            self.update_rule_stats(rule['id'], rule_results)
            
        except Exception as e:
            error_msg = f"Rule crawling failed: {str(e)}"
            rule_results['errors'].append(error_msg)
            logger.error(error_msg)
        
        return rule_results

    def search_tweets(self, query: str, max_results: int, config: Dict[str, Any]) -> Dict[str, Any]:
        """Search tweets using Twitter API v2."""
        params = {
            'query': query,
            'max_results': max_results,
            'tweet.fields': 'id,text,author_id,created_at,lang,public_metrics,context_annotations,entities',
            'user.fields': 'id,name,username,description,public_metrics,verified',
            'expansions': 'author_id'
        }
        
        # Add optional parameters
        if config.get('since_id'):
            params['since_id'] = config['since_id']
        if config.get('start_time'):
            params['start_time'] = config['start_time']
        if config.get('end_time'):
            params['end_time'] = config['end_time']
        
        try:
            response = requests.get(
                f"{self.base_url}/tweets/search/recent",
                headers=self.headers,
                params=params,
                timeout=30
            )
            response.raise_for_status()
            return response.json()
            
        except requests.exceptions.RequestException as e:
            logger.error(f"Twitter API request failed: {str(e)}")
            raise

    def process_tweets(self, tweets: List[Dict[str, Any]], rule: Dict[str, Any], includes: Dict[str, Any]) -> Dict[str, Any]:
        """Process and store tweets that match rule criteria."""
        results = {
            'processed': 0,
            'stored': 0,
            'errors': []
        }
        
        # Map users by ID for quick lookup
        users = {user['id']: user for user in includes.get('users', [])}
        
        for tweet in tweets:
            try:
                results['processed'] += 1
                
                # Get author info
                author = users.get(tweet['author_id'], {})
                
                # Prepare metadata for content matching
                metadata = {
                    'engagement': self.calculate_engagement(tweet),
                    'follower_count': author.get('public_metrics', {}).get('followers_count', 0),
                    'language': tweet.get('lang', 'unknown'),
                    'author_verified': author.get('verified', False)
                }
                
                # Check if tweet matches rule criteria
                if self.matches_rule_criteria(tweet['text'], rule, metadata):
                    if self.store_tweet(tweet, author, rule, metadata):
                        results['stored'] += 1
                        
            except Exception as e:
                error_msg = f"Failed to process tweet {tweet.get('id', 'unknown')}: {str(e)}"
                results['errors'].append(error_msg)
                logger.error(error_msg)
        
        return results

    def store_tweet(self, tweet: Dict[str, Any], author: Dict[str, Any], rule: Dict[str, Any], metadata: Dict[str, Any]) -> bool:
        """Store tweet in the database."""
        try:
            conn = self.get_db_connection()
            cursor = conn.cursor()
            
            # Check if tweet already exists
            cursor.execute(
                "SELECT id FROM social_media_posts WHERE platform = 'twitter' AND external_id = %s",
                (tweet['id'],)
            )
            
            if cursor.fetchone():
                return False  # Already exists
            
            # Insert new tweet
            insert_query = """
                INSERT INTO social_media_posts (
                    platform, external_id, post_type, content, author_username, author_display_name,
                    author_id, author_followers, author_verified, engagement_metrics, metadata,
                    matched_keywords, posted_at, crawler_rule_id, processing_status, created_at, updated_at
                ) VALUES (
                    'twitter', %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, 'pending', NOW(), NOW()
                )
            """
            
            # Prepare data
            engagement_metrics = {
                'likes': tweet.get('public_metrics', {}).get('like_count', 0),
                'retweets': tweet.get('public_metrics', {}).get('retweet_count', 0),
                'replies': tweet.get('public_metrics', {}).get('reply_count', 0),
                'quotes': tweet.get('public_metrics', {}).get('quote_count', 0)
            }
            
            matched_keywords = self.get_matched_keywords(tweet['text'], rule.get('keywords', []))
            
            cursor.execute(insert_query, (
                tweet['id'],
                self.determine_tweet_type(tweet),
                tweet['text'],
                author.get('username'),
                author.get('name'),
                tweet['author_id'],
                author.get('public_metrics', {}).get('followers_count', 0),
                author.get('verified', False),
                json.dumps(engagement_metrics),
                json.dumps(metadata),
                json.dumps(matched_keywords),
                tweet.get('created_at'),
                rule['id']
            ))
            
            conn.commit()
            cursor.close()
            conn.close()
            
            return True
            
        except Exception as e:
            logger.error(f"Failed to store tweet: {str(e)}")
            return False

    def build_search_query(self, rule: Dict[str, Any]) -> str:
        """Build Twitter search query from rule criteria."""
        query_parts = []
        
        # Add keywords
        keywords = rule.get('keywords', [])
        if keywords:
            keyword_parts = []
            for keyword in keywords:
                if ' ' in keyword:
                    keyword_parts.append(f'"{keyword}"')
                else:
                    keyword_parts.append(keyword)
            query_parts.append(f"({' OR '.join(keyword_parts)})")
        
        # Add hashtags
        hashtags = rule.get('hashtags', [])
        if hashtags:
            hashtag_parts = [f"#{tag.lstrip('#')}" for tag in hashtags]
            query_parts.append(f"({' OR '.join(hashtag_parts)})")
        
        # Add accounts
        accounts = rule.get('accounts', [])
        if accounts:
            account_parts = [f"from:{account.lstrip('@')}" for account in accounts]
            query_parts.append(f"({' OR '.join(account_parts)})")
        
        # Add exclude keywords
        exclude_keywords = rule.get('exclude_keywords', [])
        for exclude in exclude_keywords:
            query_parts.append(f"-{exclude}")
        
        # Add language filter
        language = rule.get('language', 'en')
        if language and language != 'all':
            query_parts.append(f"lang:{language}")
        
        return ' '.join(query_parts)

    def matches_rule_criteria(self, text: str, rule: Dict[str, Any], metadata: Dict[str, Any]) -> bool:
        """Check if content matches rule criteria."""
        # Check excluded keywords first
        exclude_keywords = rule.get('exclude_keywords', [])
        text_lower = text.lower()
        for exclude in exclude_keywords:
            if exclude.lower() in text_lower:
                return False
        
        # Check keywords
        keywords = rule.get('keywords', [])
        if keywords:
            keyword_match = False
            for keyword in keywords:
                if keyword.lower() in text_lower:
                    keyword_match = True
                    break
            if not keyword_match:
                return False
        
        # Check engagement threshold
        engagement_threshold = rule.get('engagement_threshold')
        if engagement_threshold and metadata.get('engagement', 0) < engagement_threshold:
            return False
        
        # Check follower threshold
        follower_threshold = rule.get('follower_threshold')
        if follower_threshold and metadata.get('follower_count', 0) < follower_threshold:
            return False
        
        return True

    def calculate_engagement(self, tweet: Dict[str, Any]) -> int:
        """Calculate total engagement for a tweet."""
        metrics = tweet.get('public_metrics', {})
        return (
            metrics.get('like_count', 0) +
            metrics.get('retweet_count', 0) +
            metrics.get('reply_count', 0) +
            metrics.get('quote_count', 0)
        )

    def determine_tweet_type(self, tweet: Dict[str, Any]) -> str:
        """Determine the type of tweet."""
        if tweet.get('referenced_tweets'):
            for ref in tweet['referenced_tweets']:
                if ref.get('type') == 'retweeted':
                    return 'retweet'
                elif ref.get('type') == 'quoted':
                    return 'quote'
                elif ref.get('type') == 'replied_to':
                    return 'reply'
        return 'original'

    def get_matched_keywords(self, text: str, keywords: List[str]) -> List[str]:
        """Get keywords that match in the text."""
        matched = []
        text_lower = text.lower()
        for keyword in keywords:
            if keyword.lower() in text_lower:
                matched.append(keyword)
        return matched

    def get_active_crawler_rules(self, rule_ids: List[int] = None) -> List[Dict[str, Any]]:
        """Get active crawler rules from database."""
        try:
            conn = self.get_db_connection()
            cursor = conn.cursor(cursor_factory=RealDictCursor)
            
            query = """
                SELECT * FROM crawler_rules 
                WHERE active = true 
                AND 'twitter' = ANY(platforms::text[])
                AND (start_date IS NULL OR start_date <= NOW())
                AND (end_date IS NULL OR end_date >= NOW())
            """
            params = []
            
            if rule_ids:
                query += " AND id = ANY(%s)"
                params.append(rule_ids)
            
            query += " ORDER BY priority ASC"
            
            cursor.execute(query, params)
            rules = cursor.fetchall()
            
            cursor.close()
            conn.close()
            
            return [dict(rule) for rule in rules]
            
        except Exception as e:
            logger.error(f"Failed to get crawler rules: {str(e)}")
            return []

    def update_rule_stats(self, rule_id: int, results: Dict[str, Any]) -> None:
        """Update rule statistics in database."""
        try:
            conn = self.get_db_connection()
            cursor = conn.cursor()
            
            stats = {
                'posts_found': results.get('posts_found', 0),
                'posts_processed': results.get('posts_processed', 0),
                'platform': 'twitter',
                'timestamp': datetime.now(timezone.utc).isoformat(),
                'lambda_execution': True
            }
            
            update_query = """
                UPDATE crawler_rules SET 
                    last_crawl_at = NOW(),
                    last_crawl_stats = %s,
                    total_posts_found = total_posts_found + %s,
                    total_posts_processed = total_posts_processed + %s
                WHERE id = %s
            """
            
            cursor.execute(update_query, (
                json.dumps(stats),
                results.get('posts_found', 0),
                results.get('posts_processed', 0),
                rule_id
            ))
            
            conn.commit()
            cursor.close()
            conn.close()
            
        except Exception as e:
            logger.error(f"Failed to update rule stats: {str(e)}")

    def get_db_connection(self):
        """Get database connection."""
        return psycopg2.connect(
            host=self.db_host,
            database=self.db_name,
            user=self.db_user,
            password=self.db_password,
            sslmode='require'
        )

# Lambda entry point
def lambda_handler(event, context):
    """AWS Lambda entry point."""
    crawler = TwitterCrawlerLambda()
    return crawler.lambda_handler(event, context)
