#!/usr/bin/env python3
"""
Keyword Manager Lambda Function
Manages keyword rules for the social media crawler
"""

import json
import os
import logging
from datetime import datetime
from typing import Dict, List, Any, Optional
import psycopg2
from psycopg2.extras import RealDictCursor

# Configure logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

class KeywordManager:
    """Manages keyword rules for social media crawling"""
    
    def __init__(self, connection_string: str):
        self.connection_string = connection_string
    
    def get_all_rules(self) -> List[Dict[str, Any]]:
        """Get all keyword rules"""
        try:
            with psycopg2.connect(self.connection_string) as conn:
                with conn.cursor(cursor_factory=RealDictCursor) as cursor:
                    cursor.execute("""
                        SELECT id, name, keywords, platforms, category, priority, 
                               match_type, case_sensitive, is_active, 
                               created_at, updated_at
                        FROM crawler_keyword_rules 
                        ORDER BY priority DESC, created_at DESC
                    """)
                    
                    rules = []
                    for row in cursor.fetchall():
                        rule = dict(row)
                        # Parse JSON fields
                        rule['keywords'] = json.loads(rule['keywords']) if isinstance(rule['keywords'], str) else rule['keywords']
                        rule['platforms'] = json.loads(rule['platforms']) if isinstance(rule['platforms'], str) else rule['platforms']
                        
                        # Convert datetime objects to ISO strings
                        if rule['created_at']:
                            rule['created_at'] = rule['created_at'].isoformat()
                        if rule['updated_at']:
                            rule['updated_at'] = rule['updated_at'].isoformat()
                        
                        rules.append(rule)
                    
                    return rules
                    
        except Exception as e:
            logger.error(f"Error fetching keyword rules: {str(e)}")
            raise
    
    def get_rule_by_id(self, rule_id: int) -> Optional[Dict[str, Any]]:
        """Get a specific keyword rule by ID"""
        try:
            with psycopg2.connect(self.connection_string) as conn:
                with conn.cursor(cursor_factory=RealDictCursor) as cursor:
                    cursor.execute("""
                        SELECT id, name, keywords, platforms, category, priority, 
                               match_type, case_sensitive, is_active, 
                               created_at, updated_at
                        FROM crawler_keyword_rules 
                        WHERE id = %s
                    """, (rule_id,))
                    
                    row = cursor.fetchone()
                    if not row:
                        return None
                    
                    rule = dict(row)
                    # Parse JSON fields
                    rule['keywords'] = json.loads(rule['keywords']) if isinstance(rule['keywords'], str) else rule['keywords']
                    rule['platforms'] = json.loads(rule['platforms']) if isinstance(rule['platforms'], str) else rule['platforms']
                    
                    # Convert datetime objects to ISO strings
                    if rule['created_at']:
                        rule['created_at'] = rule['created_at'].isoformat()
                    if rule['updated_at']:
                        rule['updated_at'] = rule['updated_at'].isoformat()
                    
                    return rule
                    
        except Exception as e:
            logger.error(f"Error fetching keyword rule {rule_id}: {str(e)}")
            raise
    
    def create_rule(self, rule_data: Dict[str, Any]) -> Dict[str, Any]:
        """Create a new keyword rule"""
        try:
            # Validate required fields
            required_fields = ['name', 'keywords', 'platforms', 'category']
            for field in required_fields:
                if field not in rule_data:
                    raise ValueError(f"Missing required field: {field}")
            
            # Set defaults
            rule_data.setdefault('priority', 5)
            rule_data.setdefault('match_type', 'any')
            rule_data.setdefault('case_sensitive', False)
            rule_data.setdefault('is_active', True)
            
            with psycopg2.connect(self.connection_string) as conn:
                with conn.cursor(cursor_factory=RealDictCursor) as cursor:
                    cursor.execute("""
                        INSERT INTO crawler_keyword_rules 
                        (name, keywords, platforms, category, priority, match_type, 
                         case_sensitive, is_active, created_at, updated_at)
                        VALUES (%(name)s, %(keywords)s, %(platforms)s, %(category)s, 
                                %(priority)s, %(match_type)s, %(case_sensitive)s, 
                                %(is_active)s, NOW(), NOW())
                        RETURNING id, created_at, updated_at
                    """, {
                        'name': rule_data['name'],
                        'keywords': json.dumps(rule_data['keywords']),
                        'platforms': json.dumps(rule_data['platforms']),
                        'category': rule_data['category'],
                        'priority': rule_data['priority'],
                        'match_type': rule_data['match_type'],
                        'case_sensitive': rule_data['case_sensitive'],
                        'is_active': rule_data['is_active']
                    })
                    
                    result = cursor.fetchone()
                    rule_data['id'] = result['id']
                    rule_data['created_at'] = result['created_at'].isoformat()
                    rule_data['updated_at'] = result['updated_at'].isoformat()
                    
                    conn.commit()
                    
                    logger.info(f"Created keyword rule: {rule_data['name']} (ID: {rule_data['id']})")
                    return rule_data
                    
        except Exception as e:
            logger.error(f"Error creating keyword rule: {str(e)}")
            raise
    
    def update_rule(self, rule_id: int, rule_data: Dict[str, Any]) -> Optional[Dict[str, Any]]:
        """Update an existing keyword rule"""
        try:
            # Get existing rule
            existing_rule = self.get_rule_by_id(rule_id)
            if not existing_rule:
                return None
            
            # Update fields
            updatable_fields = ['name', 'keywords', 'platforms', 'category', 'priority', 
                               'match_type', 'case_sensitive', 'is_active']
            
            update_data = {}
            for field in updatable_fields:
                if field in rule_data:
                    if field in ['keywords', 'platforms']:
                        update_data[field] = json.dumps(rule_data[field])
                    else:
                        update_data[field] = rule_data[field]
            
            if not update_data:
                return existing_rule  # No changes
            
            # Build update query
            set_clauses = [f"{field} = %({field})s" for field in update_data.keys()]
            update_data['rule_id'] = rule_id
            
            with psycopg2.connect(self.connection_string) as conn:
                with conn.cursor(cursor_factory=RealDictCursor) as cursor:
                    cursor.execute(f"""
                        UPDATE crawler_keyword_rules 
                        SET {', '.join(set_clauses)}, updated_at = NOW()
                        WHERE id = %(rule_id)s
                        RETURNING updated_at
                    """, update_data)
                    
                    result = cursor.fetchone()
                    if result:
                        conn.commit()
                        logger.info(f"Updated keyword rule ID: {rule_id}")
                        return self.get_rule_by_id(rule_id)
                    else:
                        return None
                        
        except Exception as e:
            logger.error(f"Error updating keyword rule {rule_id}: {str(e)}")
            raise
    
    def delete_rule(self, rule_id: int) -> bool:
        """Delete a keyword rule"""
        try:
            with psycopg2.connect(self.connection_string) as conn:
                with conn.cursor() as cursor:
                    cursor.execute("""
                        DELETE FROM crawler_keyword_rules 
                        WHERE id = %s
                    """, (rule_id,))
                    
                    deleted_count = cursor.rowcount
                    conn.commit()
                    
                    if deleted_count > 0:
                        logger.info(f"Deleted keyword rule ID: {rule_id}")
                        return True
                    else:
                        return False
                        
        except Exception as e:
            logger.error(f"Error deleting keyword rule {rule_id}: {str(e)}")
            raise
    
    def get_active_rules(self) -> List[Dict[str, Any]]:
        """Get only active keyword rules"""
        all_rules = self.get_all_rules()
        return [rule for rule in all_rules if rule.get('is_active', False)]
    
    def get_rules_by_platform(self, platform: str) -> List[Dict[str, Any]]:
        """Get rules for a specific platform"""
        all_rules = self.get_all_rules()
        return [
            rule for rule in all_rules 
            if rule.get('is_active', False) and platform in rule.get('platforms', [])
        ]
    
    def get_rules_stats(self) -> Dict[str, Any]:
        """Get statistics about keyword rules"""
        try:
            with psycopg2.connect(self.connection_string) as conn:
                with conn.cursor(cursor_factory=RealDictCursor) as cursor:
                    # Get basic counts
                    cursor.execute("""
                        SELECT 
                            COUNT(*) as total_rules,
                            COUNT(*) FILTER (WHERE is_active = true) as active_rules,
                            COUNT(DISTINCT category) as categories,
                            AVG(priority) as avg_priority
                        FROM crawler_keyword_rules
                    """)
                    
                    basic_stats = dict(cursor.fetchone())
                    
                    # Get category breakdown
                    cursor.execute("""
                        SELECT category, COUNT(*) as count
                        FROM crawler_keyword_rules
                        WHERE is_active = true
                        GROUP BY category
                        ORDER BY count DESC
                    """)
                    
                    categories = {row['category']: row['count'] for row in cursor.fetchall()}
                    
                    # Get platform breakdown
                    cursor.execute("""
                        SELECT platforms, COUNT(*) as count
                        FROM crawler_keyword_rules
                        WHERE is_active = true
                        GROUP BY platforms
                    """)
                    
                    platform_stats = {}
                    for row in cursor.fetchall():
                        platforms = json.loads(row['platforms']) if isinstance(row['platforms'], str) else row['platforms']
                        for platform in platforms:
                            platform_stats[platform] = platform_stats.get(platform, 0) + row['count']
                    
                    return {
                        'total_rules': int(basic_stats['total_rules']),
                        'active_rules': int(basic_stats['active_rules']),
                        'categories_count': int(basic_stats['categories']),
                        'average_priority': float(basic_stats['avg_priority']) if basic_stats['avg_priority'] else 0.0,
                        'categories': categories,
                        'platforms': platform_stats
                    }
                    
        except Exception as e:
            logger.error(f"Error getting rules stats: {str(e)}")
            raise

def lambda_handler(event, context):
    """AWS Lambda entry point"""
    try:
        connection_string = os.getenv('DATABASE_URL', '')
        if not connection_string:
            return {
                'statusCode': 500,
                'headers': {
                    'Content-Type': 'application/json',
                    'Access-Control-Allow-Origin': '*'
                },
                'body': json.dumps({'error': 'Database connection not configured'})
            }
        
        manager = KeywordManager(connection_string)
        
        # Get HTTP method and path
        http_method = event.get('httpMethod', event.get('requestContext', {}).get('http', {}).get('method', 'GET'))
        path = event.get('path', event.get('rawPath', '/keywords'))
        
        # Parse path parameters
        path_params = event.get('pathParameters') or {}
        rule_id = path_params.get('id')
        
        # Parse query parameters
        query_params = event.get('queryStringParameters') or {}
        
        # Parse request body
        body = {}
        if event.get('body'):
            try:
                body = json.loads(event['body'])
            except json.JSONDecodeError:
                return {
                    'statusCode': 400,
                    'headers': {
                        'Content-Type': 'application/json',
                        'Access-Control-Allow-Origin': '*'
                    },
                    'body': json.dumps({'error': 'Invalid JSON in request body'})
                }
        
        # Route requests
        if http_method == 'GET':
            if rule_id:
                # Get specific rule
                rule = manager.get_rule_by_id(int(rule_id))
                if rule:
                    result = rule
                else:
                    return {
                        'statusCode': 404,
                        'headers': {
                            'Content-Type': 'application/json',
                            'Access-Control-Allow-Origin': '*'
                        },
                        'body': json.dumps({'error': 'Rule not found'})
                    }
            elif 'stats' in query_params:
                # Get statistics
                result = manager.get_rules_stats()
            elif 'platform' in query_params:
                # Get rules for specific platform
                result = manager.get_rules_by_platform(query_params['platform'])
            elif 'active' in query_params:
                # Get only active rules
                result = manager.get_active_rules()
            else:
                # Get all rules
                result = manager.get_all_rules()
                
        elif http_method == 'POST':
            # Create new rule
            result = manager.create_rule(body)
            
        elif http_method == 'PUT' and rule_id:
            # Update existing rule
            result = manager.update_rule(int(rule_id), body)
            if result is None:
                return {
                    'statusCode': 404,
                    'headers': {
                        'Content-Type': 'application/json',
                        'Access-Control-Allow-Origin': '*'
                    },
                    'body': json.dumps({'error': 'Rule not found'})
                }
                
        elif http_method == 'DELETE' and rule_id:
            # Delete rule
            success = manager.delete_rule(int(rule_id))
            if success:
                result = {'message': 'Rule deleted successfully'}
            else:
                return {
                    'statusCode': 404,
                    'headers': {
                        'Content-Type': 'application/json',
                        'Access-Control-Allow-Origin': '*'
                    },
                    'body': json.dumps({'error': 'Rule not found'})
                }
        else:
            return {
                'statusCode': 405,
                'headers': {
                    'Content-Type': 'application/json',
                    'Access-Control-Allow-Origin': '*'
                },
                'body': json.dumps({'error': 'Method not allowed'})
            }
        
        return {
            'statusCode': 200,
            'headers': {
                'Content-Type': 'application/json',
                'Access-Control-Allow-Origin': '*'
            },
            'body': json.dumps(result, default=str)
        }
        
    except ValueError as e:
        return {
            'statusCode': 400,
            'headers': {
                'Content-Type': 'application/json',
                'Access-Control-Allow-Origin': '*'
            },
            'body': json.dumps({'error': str(e)})
        }
        
    except Exception as e:
        logger.error(f"Keyword manager error: {str(e)}")
        
        return {
            'statusCode': 500,
            'headers': {
                'Content-Type': 'application/json',
                'Access-Control-Allow-Origin': '*'
            },
            'body': json.dumps({
                'error': 'Internal server error',
                'timestamp': datetime.utcnow().isoformat()
            })
        }

# For local testing
if __name__ == "__main__":
    # Test event for getting all rules
    test_event = {
        'httpMethod': 'GET',
        'path': '/keywords',
        'queryStringParameters': None,
        'pathParameters': None,
        'body': None
    }
    
    result = lambda_handler(test_event, None)
    print(json.dumps(result, indent=2))
