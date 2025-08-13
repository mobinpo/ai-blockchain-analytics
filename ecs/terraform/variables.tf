# Core Variables
variable "aws_region" {
  description = "AWS region for resources"
  type        = string
  default     = "us-east-1"
}

variable "environment" {
  description = "Environment name (production, staging, development)"
  type        = string
  default     = "production"
}

variable "vpc_cidr" {
  description = "CIDR block for VPC"
  type        = string
  default     = "10.0.0.0/16"
}

# Database Variables
variable "db_name" {
  description = "Database name"
  type        = string
  default     = "ai_blockchain_analytics"
}

variable "db_username" {
  description = "Database username"
  type        = string
  default     = "ai_blockchain_user"
}

variable "db_password" {
  description = "Database password"
  type        = string
  sensitive   = true
}

variable "rds_instance_class" {
  description = "RDS instance class"
  type        = string
  default     = "db.t3.large"
}

variable "rds_allocated_storage" {
  description = "RDS allocated storage in GB"
  type        = number
  default     = 100
}

variable "rds_max_allocated_storage" {
  description = "RDS maximum allocated storage in GB"
  type        = number
  default     = 1000
}

# Redis Variables
variable "redis_node_type" {
  description = "ElastiCache node type"
  type        = string
  default     = "cache.t3.medium"
}

variable "redis_num_cache_nodes" {
  description = "Number of cache nodes"
  type        = number
  default     = 2
}

variable "redis_auth_token" {
  description = "Redis authentication token"
  type        = string
  sensitive   = true
}

# EFS Variables
variable "efs_provisioned_throughput" {
  description = "EFS provisioned throughput in MiB/s"
  type        = number
  default     = 100
}

# Monitoring Variables
variable "sentry_dsn" {
  description = "Sentry DSN for error tracking"
  type        = string
  sensitive   = true
}

variable "telescope_allowed_ips" {
  description = "Comma-separated list of IPs allowed to access Telescope"
  type        = string
  default     = "127.0.0.1,::1,10.0.0.0/8,172.16.0.0/12,192.168.0.0/16"
}

variable "telescope_admin_token" {
  description = "Admin token for Telescope access"
  type        = string
  sensitive   = true
}

variable "alert_email" {
  description = "Email address for CloudWatch alerts"
  type        = string
  default     = ""
}

# ECS Variables
variable "ecs_task_cpu" {
  description = "ECS task CPU units"
  type        = number
  default     = 2048
}

variable "ecs_task_memory" {
  description = "ECS task memory in MB"
  type        = number
  default     = 4096
}

variable "ecs_service_desired_count" {
  description = "Desired number of ECS service instances"
  type        = number
  default     = 3
}

variable "ecs_service_max_capacity" {
  description = "Maximum number of ECS service instances"
  type        = number
  default     = 20
}

variable "ecs_service_min_capacity" {
  description = "Minimum number of ECS service instances"
  type        = number
  default     = 3
}