# Staging Environment Configuration
# AI Blockchain Analytics - ECS Deployment

environment = "staging"
region     = "us-east-1"

# VPC Configuration
vpc_cidr             = "10.1.0.0/16"
availability_zones   = ["us-east-1a", "us-east-1b"]
private_subnet_cidrs = ["10.1.1.0/24", "10.1.2.0/24"]
public_subnet_cidrs  = ["10.1.101.0/24", "10.1.102.0/24"]

# ECS Configuration
ecs_cluster_name = "ai-blockchain-staging-cluster"

# Application Load Balancer
alb_name = "ai-blockchain-staging-alb"
enable_deletion_protection = false
enable_http2 = true

# ECS Service Configuration
app_service_name = "roadrunner-app"
app_desired_count = 2
app_min_capacity = 1
app_max_capacity = 5

worker_service_name = "horizon-worker"
worker_desired_count = 1
worker_min_capacity = 1
worker_max_capacity = 3

# RDS PostgreSQL Configuration (Smaller for staging)
rds_instance_class = "db.r6g.large"
rds_allocated_storage = 20
rds_max_allocated_storage = 100
rds_backup_retention_period = 7
rds_backup_window = "03:00-04:00"
rds_maintenance_window = "sun:04:00-sun:05:00"
rds_multi_az = false
rds_storage_encrypted = true

# Database
db_name = "ai_blockchain_staging"
db_username = "ai_blockchain_user"
db_password = "CHANGE_ME_STAGING_DB_PASSWORD"

# ElastiCache Redis Configuration (Smaller for staging)
redis_node_type = "cache.r7g.large"
redis_num_cache_nodes = 1
redis_parameter_group_family = "redis7"
redis_engine_version = "7.0"
redis_port = 6379
redis_auth_token = "CHANGE_ME_STAGING_REDIS_AUTH_TOKEN"

# EFS Configuration
efs_provisioned_throughput = 50
efs_performance_mode = "generalPurpose"
efs_throughput_mode = "provisioned"

# Auto Scaling Configuration
app_cpu_target = 75
app_memory_target = 85
app_scale_up_cooldown = 300
app_scale_down_cooldown = 600

worker_cpu_target = 80
worker_memory_target = 90
worker_scale_up_cooldown = 180
worker_scale_down_cooldown = 300

# CloudWatch Logs
log_retention_in_days = 14

# WAF Configuration
enable_waf = true
waf_rate_limit = 1000

# Route 53 Configuration  
domain_name = "ai-blockchain-analytics.com"
subdomain = "staging-api"
create_route53_records = true

# ACM Certificate
ssl_certificate_domain = "*.ai-blockchain-analytics.com"

# Monitoring & Alerting
enable_detailed_monitoring = false
sns_topic_arn = "arn:aws:sns:us-east-1:ACCOUNT_ID:ai-blockchain-staging-alerts"

# Backup Configuration
enable_automated_backups = true
backup_retention_period = 7

# Cost Optimization (Use spot instances for staging)
enable_spot_instances = true
reserved_instance_utilization = false

# Security Configuration
enable_vpc_flow_logs = true
enable_guardduty = false
enable_config = false

# Tags
common_tags = {
  Project     = "AI-Blockchain-Analytics"
  Environment = "staging"
  Team        = "Engineering"
  Owner       = "DevOps"
  CostCenter  = "Technology"
  Backup      = "Optional"
  Monitoring  = "Standard"
}

# Application Configuration
app_environment_variables = {
  APP_ENV = "staging"
  APP_DEBUG = "false"
  OCTANE_SERVER = "roadrunner"
  CACHE_DRIVER = "redis"
  QUEUE_CONNECTION = "redis"
  SESSION_DRIVER = "redis"
  TELESCOPE_ENABLED = "true"
  TELESCOPE_PRODUCTION_ENABLED = "true"
  SENTRY_ENVIRONMENT = "staging"
  SENTRY_TRACES_SAMPLE_RATE = "0.5"
  RR_WORKERS = "8"
  RR_MAX_JOBS = "2000"
  RR_WORKER_MEMORY_LIMIT = "256"
}