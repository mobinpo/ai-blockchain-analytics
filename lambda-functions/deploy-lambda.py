#!/usr/bin/env python3
"""
Lambda Deployment Script

Deploy crawler Lambda functions to AWS with proper configuration.
"""

import boto3
import zipfile
import os
import json
import subprocess
import sys
from pathlib import Path

class LambdaDeployer:
    def __init__(self):
        self.lambda_client = boto3.client('lambda')
        self.iam_client = boto3.client('iam')
        
    def deploy_crawler_lambda(self, crawler_type: str, function_name: str = None):
        """Deploy a specific crawler Lambda function."""
        if not function_name:
            function_name = f'ai-blockchain-{crawler_type}-crawler'
        
        crawler_dir = Path(f'{crawler_type}-crawler')
        if not crawler_dir.exists():
            print(f"Error: Crawler directory {crawler_dir} not found")
            return False
        
        print(f"Deploying {crawler_type} crawler Lambda function...")
        
        # Create deployment package
        zip_path = self.create_deployment_package(crawler_dir, function_name)
        
        # Create or update Lambda function
        success = self.deploy_function(function_name, zip_path, crawler_type)
        
        # Cleanup
        os.remove(zip_path)
        
        return success
    
    def create_deployment_package(self, crawler_dir: Path, function_name: str) -> str:
        """Create deployment ZIP package for Lambda."""
        print(f"Creating deployment package for {function_name}...")
        
        # Install dependencies
        self.install_dependencies(crawler_dir)
        
        # Create ZIP file
        zip_path = f'{function_name}.zip'
        with zipfile.ZipFile(zip_path, 'w', zipfile.ZIP_DEFLATED) as zipf:
            # Add Python dependencies
            deps_dir = crawler_dir / 'deps'
            if deps_dir.exists():
                for root, dirs, files in os.walk(deps_dir):
                    for file in files:
                        file_path = os.path.join(root, file)
                        arcname = os.path.relpath(file_path, deps_dir)
                        zipf.write(file_path, arcname)
            
            # Add Lambda function code
            lambda_file = crawler_dir / 'lambda_function.py'
            if lambda_file.exists():
                zipf.write(lambda_file, 'lambda_function.py')
        
        print(f"Deployment package created: {zip_path}")
        return zip_path
    
    def install_dependencies(self, crawler_dir: Path):
        """Install Python dependencies for Lambda."""
        requirements_file = crawler_dir / 'requirements.txt'
        if not requirements_file.exists():
            print(f"No requirements.txt found in {crawler_dir}")
            return
        
        deps_dir = crawler_dir / 'deps'
        deps_dir.mkdir(exist_ok=True)
        
        print("Installing dependencies...")
        subprocess.run([
            sys.executable, '-m', 'pip', 'install',
            '-r', str(requirements_file),
            '-t', str(deps_dir),
            '--upgrade'
        ], check=True)
    
    def deploy_function(self, function_name: str, zip_path: str, crawler_type: str) -> bool:
        """Deploy or update Lambda function."""
        try:
            # Read ZIP file
            with open(zip_path, 'rb') as f:
                zip_content = f.read()
            
            # Check if function exists
            try:
                self.lambda_client.get_function(FunctionName=function_name)
                function_exists = True
            except self.lambda_client.exceptions.ResourceNotFoundException:
                function_exists = False
            
            if function_exists:
                # Update existing function
                print(f"Updating existing function {function_name}...")
                response = self.lambda_client.update_function_code(
                    FunctionName=function_name,
                    ZipFile=zip_content
                )
            else:
                # Create new function
                print(f"Creating new function {function_name}...")
                
                # Create execution role if needed
                role_arn = self.ensure_execution_role(function_name)
                
                response = self.lambda_client.create_function(
                    FunctionName=function_name,
                    Runtime='python3.9',
                    Role=role_arn,
                    Handler='lambda_function.lambda_handler',
                    Code={'ZipFile': zip_content},
                    Description=f'AI Blockchain Analytics {crawler_type.title()} Crawler',
                    Timeout=300,  # 5 minutes
                    MemorySize=512,
                    Environment={
                        'Variables': self.get_environment_variables(crawler_type)
                    },
                    Tags={
                        'Project': 'ai-blockchain-analytics',
                        'Component': 'crawler',
                        'Platform': crawler_type
                    }
                )
            
            print(f"Function {function_name} deployed successfully!")
            print(f"Function ARN: {response['FunctionArn']}")
            
            # Configure triggers if needed
            self.configure_triggers(function_name, crawler_type)
            
            return True
            
        except Exception as e:
            print(f"Failed to deploy function {function_name}: {str(e)}")
            return False
    
    def ensure_execution_role(self, function_name: str) -> str:
        """Ensure Lambda execution role exists."""
        role_name = f'{function_name}-execution-role'
        
        try:
            # Check if role exists
            response = self.iam_client.get_role(RoleName=role_name)
            return response['Role']['Arn']
        except self.iam_client.exceptions.NoSuchEntityException:
            pass
        
        # Create role
        print(f"Creating execution role {role_name}...")
        
        assume_role_policy = {
            "Version": "2012-10-17",
            "Statement": [
                {
                    "Effect": "Allow",
                    "Principal": {
                        "Service": "lambda.amazonaws.com"
                    },
                    "Action": "sts:AssumeRole"
                }
            ]
        }
        
        response = self.iam_client.create_role(
            RoleName=role_name,
            AssumeRolePolicyDocument=json.dumps(assume_role_policy),
            Description=f'Execution role for {function_name} Lambda function'
        )
        
        role_arn = response['Role']['Arn']
        
        # Attach basic execution policy
        self.iam_client.attach_role_policy(
            RoleName=role_name,
            PolicyArn='arn:aws:iam::aws:policy/service-role/AWSLambdaBasicExecutionRole'
        )
        
        # Attach VPC execution policy if needed
        self.iam_client.attach_role_policy(
            RoleName=role_name,
            PolicyArn='arn:aws:iam::aws:policy/service-role/AWSLambdaVPCAccessExecutionRole'
        )
        
        print(f"Execution role created: {role_arn}")
        return role_arn
    
    def get_environment_variables(self, crawler_type: str) -> dict:
        """Get environment variables for Lambda function."""
        base_vars = {
            'DB_HOST': os.environ.get('DB_HOST', ''),
            'DB_NAME': os.environ.get('DB_NAME', ''),
            'DB_USER': os.environ.get('DB_USER', ''),
            'DB_PASSWORD': os.environ.get('DB_PASSWORD', ''),
        }
        
        if crawler_type == 'twitter':
            base_vars.update({
                'TWITTER_BEARER_TOKEN': os.environ.get('TWITTER_BEARER_TOKEN', ''),
                'TWITTER_API_KEY': os.environ.get('TWITTER_API_KEY', ''),
                'TWITTER_API_SECRET': os.environ.get('TWITTER_API_SECRET', ''),
            })
        elif crawler_type == 'reddit':
            base_vars.update({
                'REDDIT_CLIENT_ID': os.environ.get('REDDIT_CLIENT_ID', ''),
                'REDDIT_CLIENT_SECRET': os.environ.get('REDDIT_CLIENT_SECRET', ''),
                'REDDIT_USERNAME': os.environ.get('REDDIT_USERNAME', ''),
                'REDDIT_PASSWORD': os.environ.get('REDDIT_PASSWORD', ''),
            })
        elif crawler_type == 'telegram':
            base_vars.update({
                'TELEGRAM_BOT_TOKEN': os.environ.get('TELEGRAM_BOT_TOKEN', ''),
            })
        
        return base_vars
    
    def configure_triggers(self, function_name: str, crawler_type: str):
        """Configure CloudWatch Events triggers for scheduled crawling."""
        try:
            events_client = boto3.client('events')
            
            # Create CloudWatch Events rule for scheduled crawling
            rule_name = f'{function_name}-schedule'
            
            events_client.put_rule(
                Name=rule_name,
                ScheduleExpression='rate(15 minutes)',  # Run every 15 minutes
                Description=f'Scheduled trigger for {crawler_type} crawler',
                State='ENABLED'
            )
            
            # Add Lambda as target
            events_client.put_targets(
                Rule=rule_name,
                Targets=[
                    {
                        'Id': '1',
                        'Arn': f'arn:aws:lambda:{boto3.Session().region_name}:{boto3.client("sts").get_caller_identity()["Account"]}:function:{function_name}',
                        'Input': json.dumps({
                            'mode': 'batch',
                            'source': 'cloudwatch_events'
                        })
                    }
                ]
            )
            
            # Add permission for CloudWatch Events to invoke Lambda
            try:
                self.lambda_client.add_permission(
                    FunctionName=function_name,
                    StatementId=f'{function_name}-cloudwatch-events',
                    Action='lambda:InvokeFunction',
                    Principal='events.amazonaws.com',
                    SourceArn=f'arn:aws:events:{boto3.Session().region_name}:{boto3.client("sts").get_caller_identity()["Account"]}:rule/{rule_name}'
                )
            except self.lambda_client.exceptions.ResourceConflictException:
                # Permission already exists
                pass
            
            print(f"Scheduled trigger configured for {function_name}")
            
        except Exception as e:
            print(f"Warning: Failed to configure triggers for {function_name}: {str(e)}")
    
    def deploy_all_crawlers(self):
        """Deploy all crawler Lambda functions."""
        crawlers = ['twitter', 'reddit', 'telegram']
        results = {}
        
        for crawler in crawlers:
            print(f"\n{'='*50}")
            print(f"Deploying {crawler} crawler")
            print('='*50)
            
            results[crawler] = self.deploy_crawler_lambda(crawler)
        
        print(f"\n{'='*50}")
        print("Deployment Summary")
        print('='*50)
        
        for crawler, success in results.items():
            status = "✅ SUCCESS" if success else "❌ FAILED"
            print(f"{crawler.title()} Crawler: {status}")
        
        return results

if __name__ == '__main__':
    import argparse
    
    parser = argparse.ArgumentParser(description='Deploy crawler Lambda functions')
    parser.add_argument('--crawler', choices=['twitter', 'reddit', 'telegram', 'all'], 
                       default='all', help='Crawler type to deploy')
    parser.add_argument('--function-name', help='Custom function name')
    
    args = parser.parse_args()
    
    deployer = LambdaDeployer()
    
    if args.crawler == 'all':
        deployer.deploy_all_crawlers()
    else:
        deployer.deploy_crawler_lambda(args.crawler, args.function_name)
