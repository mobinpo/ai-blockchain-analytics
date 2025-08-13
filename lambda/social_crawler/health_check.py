#!/usr/bin/env python3
"""
Health Check Lambda Function
Checks the status of social media APIs and database connectivity
"""

import json
import os
import time
import logging
from datetime import datetime
from typing import Dict, Any
import requests
import psycopg2

# Configure logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

class HealthChecker:
    """Health check implementation"""
    
    def __init__(self):
        self.checks = {}
        self.overall_status = "healthy"
    
    def check_twitter_api(self) -> Dict[str, Any]:
        """Check Twitter API connectivity"""
        try:
            bearer_token = os.getenv('TWITTER_BEARER_TOKEN', '')
            if not bearer_token:
                return {"status": "disabled", "message": "No bearer token configured"}
            
            headers = {"Authorization": f"Bearer {bearer_token}"}
            response = requests.get(
                "https://api.twitter.com/2/tweets/search/recent",
                headers=headers,
                params={"query": "test", "max_results": 1},
                timeout=10
            )
            
            if response.status_code == 200:
                return {"status": "healthy", "response_time": response.elapsed.total_seconds()}
            elif response.status_code == 429:
                return {"status": "rate_limited", "message": "API rate limit exceeded"}
            else:
                return {"status": "error", "message": f"HTTP {response.status_code}"}
                
        except requests.exceptions.Timeout:
            return {"status": "timeout", "message": "API request timed out"}
        except Exception as e:
            return {"status": "error", "message": str(e)}
    
    def check_reddit_api(self) -> Dict[str, Any]:
        """Check Reddit API connectivity"""
        try:
            client_id = os.getenv('REDDIT_CLIENT_ID', '')
            client_secret = os.getenv('REDDIT_CLIENT_SECRET', '')
            
            if not client_id or not client_secret:
                return {"status": "disabled", "message": "No credentials configured"}
            
            # Test authentication
            auth = requests.auth.HTTPBasicAuth(client_id, client_secret)
            data = {
                'grant_type': 'client_credentials'
            }
            headers = {'User-Agent': 'HealthCheck/1.0'}
            
            response = requests.post(
                'https://www.reddit.com/api/v1/access_token',
                auth=auth,
                data=data,
                headers=headers,
                timeout=10
            )
            
            if response.status_code == 200:
                return {"status": "healthy", "response_time": response.elapsed.total_seconds()}
            else:
                return {"status": "error", "message": f"HTTP {response.status_code}"}
                
        except requests.exceptions.Timeout:
            return {"status": "timeout", "message": "API request timed out"}
        except Exception as e:
            return {"status": "error", "message": str(e)}
    
    def check_telegram_api(self) -> Dict[str, Any]:
        """Check Telegram API connectivity"""
        try:
            bot_token = os.getenv('TELEGRAM_BOT_TOKEN', '')
            if not bot_token:
                return {"status": "disabled", "message": "No bot token configured"}
            
            response = requests.get(
                f"https://api.telegram.org/bot{bot_token}/getMe",
                timeout=10
            )
            
            if response.status_code == 200:
                data = response.json()
                if data.get('ok'):
                    return {
                        "status": "healthy", 
                        "response_time": response.elapsed.total_seconds(),
                        "bot_info": data.get('result', {}).get('username', 'unknown')
                    }
                else:
                    return {"status": "error", "message": "API returned error"}
            else:
                return {"status": "error", "message": f"HTTP {response.status_code}"}
                
        except requests.exceptions.Timeout:
            return {"status": "timeout", "message": "API request timed out"}
        except Exception as e:
            return {"status": "error", "message": str(e)}
    
    def check_database(self) -> Dict[str, Any]:
        """Check database connectivity"""
        try:
            connection_string = os.getenv('DATABASE_URL', '')
            if not connection_string:
                return {"status": "disabled", "message": "No database URL configured"}
            
            start_time = time.time()
            with psycopg2.connect(connection_string) as conn:
                with conn.cursor() as cursor:
                    cursor.execute("SELECT 1")
                    result = cursor.fetchone()
                    
            response_time = time.time() - start_time
            
            if result and result[0] == 1:
                return {"status": "healthy", "response_time": response_time}
            else:
                return {"status": "error", "message": "Unexpected query result"}
                
        except psycopg2.OperationalError as e:
            return {"status": "connection_error", "message": str(e)}
        except Exception as e:
            return {"status": "error", "message": str(e)}
    
    def check_environment(self) -> Dict[str, Any]:
        """Check environment configuration"""
        required_vars = [
            'TWITTER_BEARER_TOKEN',
            'REDDIT_CLIENT_ID',
            'REDDIT_CLIENT_SECRET',
            'TELEGRAM_BOT_TOKEN',
            'DATABASE_URL'
        ]
        
        missing_vars = []
        configured_vars = []
        
        for var in required_vars:
            value = os.getenv(var, '')
            if value:
                configured_vars.append(var)
            else:
                missing_vars.append(var)
        
        status = "healthy" if len(missing_vars) == 0 else "warning"
        
        return {
            "status": status,
            "configured_variables": len(configured_vars),
            "missing_variables": missing_vars,
            "total_required": len(required_vars)
        }
    
    def run_all_checks(self) -> Dict[str, Any]:
        """Run all health checks"""
        start_time = time.time()
        
        # Run individual checks
        self.checks = {
            "twitter_api": self.check_twitter_api(),
            "reddit_api": self.check_reddit_api(),
            "telegram_api": self.check_telegram_api(),
            "database": self.check_database(),
            "environment": self.check_environment()
        }
        
        # Determine overall status
        critical_checks = ["database", "environment"]
        api_checks = ["twitter_api", "reddit_api", "telegram_api"]
        
        # Check critical systems
        critical_healthy = all(
            self.checks[check]["status"] in ["healthy", "disabled"] 
            for check in critical_checks
        )
        
        # Check API availability (at least one should be healthy)
        api_healthy = any(
            self.checks[check]["status"] == "healthy" 
            for check in api_checks
        )
        
        if not critical_healthy:
            self.overall_status = "unhealthy"
        elif not api_healthy:
            self.overall_status = "degraded"
        else:
            # Check for any warnings
            has_warnings = any(
                self.checks[check]["status"] in ["warning", "rate_limited", "timeout"]
                for check in self.checks
            )
            self.overall_status = "warning" if has_warnings else "healthy"
        
        execution_time = time.time() - start_time
        
        return {
            "overall_status": self.overall_status,
            "timestamp": datetime.utcnow().isoformat(),
            "execution_time": execution_time,
            "checks": self.checks,
            "summary": {
                "total_checks": len(self.checks),
                "healthy_checks": sum(1 for check in self.checks.values() if check["status"] == "healthy"),
                "failed_checks": sum(1 for check in self.checks.values() if check["status"] in ["error", "connection_error"]),
                "warning_checks": sum(1 for check in self.checks.values() if check["status"] in ["warning", "rate_limited", "timeout"])
            }
        }

def lambda_handler(event, context):
    """AWS Lambda entry point"""
    try:
        checker = HealthChecker()
        result = checker.run_all_checks()
        
        # Determine HTTP status code based on health
        status_code_map = {
            "healthy": 200,
            "warning": 200,
            "degraded": 503,
            "unhealthy": 503
        }
        
        status_code = status_code_map.get(result["overall_status"], 500)
        
        return {
            'statusCode': status_code,
            'headers': {
                'Content-Type': 'application/json',
                'Access-Control-Allow-Origin': '*',
                'Cache-Control': 'no-cache'
            },
            'body': json.dumps(result, indent=2)
        }
        
    except Exception as e:
        logger.error(f"Health check failed: {str(e)}")
        
        return {
            'statusCode': 500,
            'headers': {
                'Content-Type': 'application/json',
                'Access-Control-Allow-Origin': '*'
            },
            'body': json.dumps({
                'overall_status': 'error',
                'error': str(e),
                'timestamp': datetime.utcnow().isoformat()
            })
        }

# For local testing
if __name__ == "__main__":
    result = lambda_handler({}, None)
    print(json.dumps(json.loads(result['body']), indent=2))
