# RDS Subnet Group
resource "aws_db_subnet_group" "main" {
  name       = "ai-blockchain-db-subnet-group"
  subnet_ids = aws_subnet.private[*].id

  tags = {
    Name        = "ai-blockchain-db-subnet-group"
    Project     = "AI-Blockchain-Analytics"
    Environment = var.environment
  }
}

# RDS Parameter Group
resource "aws_db_parameter_group" "postgres" {
  family = "postgres16"
  name   = "ai-blockchain-postgres-params"

  parameter {
    name  = "shared_preload_libraries"
    value = "pg_stat_statements"
  }

  parameter {
    name  = "log_statement"
    value = "all"
  }

  parameter {
    name  = "log_min_duration_statement"
    value = "1000"
  }

  parameter {
    name  = "max_connections"
    value = "200"
  }

  tags = {
    Name        = "ai-blockchain-postgres-params"
    Project     = "AI-Blockchain-Analytics"
    Environment = var.environment
  }
}

# RDS Instance
resource "aws_db_instance" "postgres" {
  identifier = "ai-blockchain-postgres"

  engine                = "postgres"
  engine_version        = "16.1"
  instance_class        = var.rds_instance_class
  allocated_storage     = var.rds_allocated_storage
  max_allocated_storage = var.rds_max_allocated_storage
  storage_type          = "gp3"
  storage_encrypted     = true

  db_name  = var.db_name
  username = var.db_username
  password = var.db_password

  vpc_security_group_ids = [aws_security_group.rds.id]
  db_subnet_group_name   = aws_db_subnet_group.main.name
  parameter_group_name   = aws_db_parameter_group.postgres.name

  backup_retention_period = var.environment == "production" ? 30 : 7
  backup_window          = "03:00-04:00"
  maintenance_window     = "sun:04:00-sun:05:00"

  skip_final_snapshot       = var.environment != "production"
  final_snapshot_identifier = var.environment == "production" ? "ai-blockchain-postgres-final-snapshot" : null
  deletion_protection       = var.environment == "production"

  performance_insights_enabled = true
  monitoring_interval          = 60
  monitoring_role_arn         = aws_iam_role.rds_enhanced_monitoring.arn

  enabled_cloudwatch_logs_exports = ["postgresql", "upgrade"]

  tags = {
    Name        = "ai-blockchain-postgres"
    Project     = "AI-Blockchain-Analytics"
    Environment = var.environment
  }
}

# RDS Enhanced Monitoring Role
resource "aws_iam_role" "rds_enhanced_monitoring" {
  name = "ai-blockchain-rds-monitoring-role"

  assume_role_policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Action = "sts:AssumeRole"
        Effect = "Allow"
        Principal = {
          Service = "monitoring.rds.amazonaws.com"
        }
      }
    ]
  })
}

resource "aws_iam_role_policy_attachment" "rds_enhanced_monitoring" {
  role       = aws_iam_role.rds_enhanced_monitoring.name
  policy_arn = "arn:aws:iam::aws:policy/service-role/AmazonRDSEnhancedMonitoringRole"
}

# ElastiCache Subnet Group
resource "aws_elasticache_subnet_group" "main" {
  name       = "ai-blockchain-cache-subnet"
  subnet_ids = aws_subnet.private[*].id

  tags = {
    Name        = "ai-blockchain-cache-subnet"
    Project     = "AI-Blockchain-Analytics"
    Environment = var.environment
  }
}

# ElastiCache Parameter Group
resource "aws_elasticache_parameter_group" "redis" {
  family = "redis7"
  name   = "ai-blockchain-redis-params"

  parameter {
    name  = "maxmemory-policy"
    value = "allkeys-lru"
  }

  parameter {
    name  = "timeout"
    value = "300"
  }

  tags = {
    Name        = "ai-blockchain-redis-params"
    Project     = "AI-Blockchain-Analytics"
    Environment = var.environment
  }
}

# ElastiCache Replication Group
resource "aws_elasticache_replication_group" "redis" {
  replication_group_id       = "ai-blockchain-redis"
  description                = "Redis cluster for AI Blockchain Analytics"
  
  node_type                  = var.redis_node_type
  port                       = 6379
  parameter_group_name       = aws_elasticache_parameter_group.redis.name
  subnet_group_name          = aws_elasticache_subnet_group.main.name
  security_group_ids         = [aws_security_group.redis.id]

  num_cache_clusters         = var.redis_num_cache_nodes
  engine_version             = "7.0"
  
  at_rest_encryption_enabled = true
  transit_encryption_enabled = true
  auth_token                 = var.redis_auth_token

  automatic_failover_enabled = var.redis_num_cache_nodes > 1
  multi_az_enabled          = var.redis_num_cache_nodes > 1

  snapshot_retention_limit = var.environment == "production" ? 7 : 1
  snapshot_window         = "03:00-05:00"
  maintenance_window      = "sun:05:00-sun:07:00"

  log_delivery_configuration {
    destination      = aws_cloudwatch_log_group.redis_slow.name
    destination_type = "cloudwatch-logs"
    log_format       = "text"
    log_type         = "slow-log"
  }

  tags = {
    Name        = "ai-blockchain-redis"
    Project     = "AI-Blockchain-Analytics"
    Environment = var.environment
  }
}

# CloudWatch Log Group for Redis
resource "aws_cloudwatch_log_group" "redis_slow" {
  name              = "/aws/elasticache/ai-blockchain-redis/slow-log"
  retention_in_days = 7
}

# EFS File System for shared storage
resource "aws_efs_file_system" "main" {
  creation_token = "ai-blockchain-efs"
  encrypted      = true

  performance_mode = "generalPurpose"
  throughput_mode  = "provisioned"
  provisioned_throughput_in_mibps = var.efs_provisioned_throughput

  tags = {
    Name        = "ai-blockchain-efs"
    Project     = "AI-Blockchain-Analytics"
    Environment = var.environment
  }
}

resource "aws_efs_mount_target" "main" {
  count = length(aws_subnet.private)

  file_system_id  = aws_efs_file_system.main.id
  subnet_id       = aws_subnet.private[count.index].id
  security_groups = [aws_security_group.efs.id]
}

resource "aws_security_group" "efs" {
  name_prefix = "ai-blockchain-efs-"
  vpc_id      = aws_vpc.main.id

  ingress {
    description     = "NFS"
    from_port       = 2049
    to_port         = 2049
    protocol        = "tcp"
    security_groups = [aws_security_group.ecs_tasks.id]
  }

  tags = {
    Name        = "ai-blockchain-efs-sg"
    Project     = "AI-Blockchain-Analytics"
    Environment = var.environment
  }
}

# EFS Access Point
resource "aws_efs_access_point" "app_storage" {
  file_system_id = aws_efs_file_system.main.id

  posix_user {
    uid = 1000
    gid = 1000
  }

  root_directory {
    path = "/app-storage"
    creation_info {
      owner_uid   = 1000
      owner_gid   = 1000
      permissions = 755
    }
  }

  tags = {
    Name        = "ai-blockchain-app-storage"
    Project     = "AI-Blockchain-Analytics"
    Environment = var.environment
  }
}