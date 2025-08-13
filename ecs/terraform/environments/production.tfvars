# Production Environment Configuration
# AI Blockchain Analytics - ECS Deployment

environment = "production"
region     = "us-east-1"

# VPC Configuration
vpc_cidr             = "10.0.0.0/16"
availability_zones   = ["us-east-1a", "us-east-1b", "us-east-1c"]
private_subnet_cidrs = ["10.0.1.0/24", "10.0.2.0/24", "10.0.3.0/24"]
public_subnet_cidrs  = ["10.0.101.0/24", "10.0.102.0/24", "10.0.103.0/24"]

# ECS Configuration
ecs_cluster_name = "ai-blockchain-cluster"

# Application Load Balancer
alb_name = "ai-blockchain-alb"
enable_deletion_protection = true
enable_http2 = true

# ECS Service Configuration
app_service_name = "roadrunner-app"
app_desired_count = 3
app_min_capacity = 2
app_max_capacity = 20

worker_service_name = "horizon-worker"
worker_desired_count = 2
worker_min_capacity = 1
worker_max_capacity = 10

# RDS PostgreSQL Configuration
rds_instance_class = "db.r6g.xlarge"
rds_allocated_storage = 100
rds_max_allocated_storage = 1000
rds_backup_retention_period = 30
rds_backup_window = "03:00-04:00"
rds_maintenance_window = "sun:04:00-sun:05:00"
rds_multi_az = true
rds_storage_encrypted = true

# Database
db_name = "ai_blockchain_analytics"
db_username = "ai_blockchain_user"
db_password = "CHANGE_ME_SECURE_DB_PASSWORD"

# ElastiCache Redis Configuration  
redis_node_type = "cache.r7g.xlarge"
redis_num_cache_nodes = 2
redis_parameter_group_family = "redis7"
redis_engine_version = "7.0"
redis_port = 6379
redis_auth_token = "CHANGE_ME_REDIS_AUTH_TOKEN"

# EFS Configuration
efs_provisioned_throughput = 100
efs_performance_mode = "generalPurpose"
efs_throughput_mode = "provisioned"

# Auto Scaling Configuration
app_cpu_target = 70
app_memory_target = 80
app_scale_up_cooldown = 300
app_scale_down_cooldown = 600

worker_cpu_target = 75
worker_memory_target = 85
worker_scale_up_cooldown = 180
worker_scale_down_cooldown = 300

# CloudWatch Logs
log_retention_in_days = 30

# WAF Configuration
enable_waf = true
waf_rate_limit = 2000

# Route 53 Configuration  
domain_name = "ai-blockchain-analytics.com"
subdomain = "api"
create_route53_records = true

# ACM Certificate
ssl_certificate_domain = "*.ai-blockchain-analytics.com"

# Monitoring & Alerting
enable_detailed_monitoring = true
sns_topic_arn = "arn:aws:sns:us-east-1:ACCOUNT_ID:ai-blockchain-alerts"

# Backup Configuration
enable_automated_backups = true
backup_retention_period = 35

# Cost Optimization
enable_spot_instances = false  # Set to true for dev/staging
reserved_instance_utilization = true

# Security Configuration
enable_vpc_flow_logs = true
enable_guardduty = true
enable_config = true

# Tags
common_tags = {
  Project     = "AI-Blockchain-Analytics"
  Environment = "production"  
  Team        = "Engineering"
  Owner       = "DevOps"
  CostCenter  = "Technology"
  Backup      = "Required"
  Monitoring  = "Critical"
}

# Application Configuration
app_environment_variables = {
  APP_ENV = "production"
  APP_DEBUG = "false"
  OCTANE_SERVER = "roadrunner"
  CACHE_DRIVER = "redis"
  QUEUE_CONNECTION = "redis" 
  SESSION_DRIVER = "redis"
  TELESCOPE_ENABLED = "true"
  TELESCOPE_PRODUCTION_ENABLED = "false"
  SENTRY_ENVIRONMENT = "production"
  SENTRY_TRACES_SAMPLE_RATE = "0.1"
  RR_WORKERS = "16"
  RR_MAX_JOBS = "4000"
  RR_WORKER_MEMORY_LIMIT = "512"
}