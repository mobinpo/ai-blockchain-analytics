# CloudWatch Log Groups for Enhanced Monitoring
resource "aws_cloudwatch_log_group" "app_logs" {
  name              = "/ecs/ai-blockchain-analytics-roadrunner"
  retention_in_days = 14

  tags = {
    Name        = "ai-blockchain-analytics-app-logs"
    Project     = "AI-Blockchain-Analytics"
    Environment = var.environment
  }
}

resource "aws_cloudwatch_log_group" "telescope_logs" {
  name              = "/ecs/ai-blockchain-analytics-telescope"
  retention_in_days = 7

  tags = {
    Name        = "ai-blockchain-analytics-telescope-logs"
    Project     = "AI-Blockchain-Analytics"
    Environment = var.environment
  }
}

resource "aws_cloudwatch_log_group" "sentry_logs" {
  name              = "/ecs/ai-blockchain-analytics-sentry"
  retention_in_days = 7

  tags = {
    Name        = "ai-blockchain-analytics-sentry-logs"
    Project     = "AI-Blockchain-Analytics"
    Environment = var.environment
  }
}

# CloudWatch Alarms for Application Monitoring
resource "aws_cloudwatch_metric_alarm" "high_cpu" {
  alarm_name          = "ai-blockchain-analytics-high-cpu"
  comparison_operator = "GreaterThanThreshold"
  evaluation_periods  = "2"
  metric_name         = "CPUUtilization"
  namespace           = "AWS/ECS"
  period              = "120"
  statistic           = "Average"
  threshold           = "80"
  alarm_description   = "This metric monitors ECS CPU utilization"
  alarm_actions       = [aws_sns_topic.alerts.arn]

  dimensions = {
    ServiceName = aws_ecs_service.app.name
    ClusterName = aws_ecs_cluster.main.name
  }

  tags = {
    Name        = "ai-blockchain-analytics-high-cpu-alarm"
    Project     = "AI-Blockchain-Analytics"
    Environment = var.environment
  }
}

resource "aws_cloudwatch_metric_alarm" "high_memory" {
  alarm_name          = "ai-blockchain-analytics-high-memory"
  comparison_operator = "GreaterThanThreshold"
  evaluation_periods  = "2"
  metric_name         = "MemoryUtilization"
  namespace           = "AWS/ECS"
  period              = "120"
  statistic           = "Average"
  threshold           = "85"
  alarm_description   = "This metric monitors ECS memory utilization"
  alarm_actions       = [aws_sns_topic.alerts.arn]

  dimensions = {
    ServiceName = aws_ecs_service.app.name
    ClusterName = aws_ecs_cluster.main.name
  }

  tags = {
    Name        = "ai-blockchain-analytics-high-memory-alarm"
    Project     = "AI-Blockchain-Analytics"
    Environment = var.environment
  }
}

resource "aws_cloudwatch_metric_alarm" "database_connections" {
  alarm_name          = "ai-blockchain-analytics-high-db-connections"
  comparison_operator = "GreaterThanThreshold"
  evaluation_periods  = "2"
  metric_name         = "DatabaseConnections"
  namespace           = "AWS/RDS"
  period              = "300"
  statistic           = "Average"
  threshold           = "150"
  alarm_description   = "This metric monitors RDS database connections"
  alarm_actions       = [aws_sns_topic.alerts.arn]

  dimensions = {
    DBInstanceIdentifier = aws_db_instance.postgres.id
  }

  tags = {
    Name        = "ai-blockchain-analytics-high-db-connections-alarm"
    Project     = "AI-Blockchain-Analytics"
    Environment = var.environment
  }
}

resource "aws_cloudwatch_metric_alarm" "redis_cpu" {
  alarm_name          = "ai-blockchain-analytics-redis-high-cpu"
  comparison_operator = "GreaterThanThreshold"
  evaluation_periods  = "2"
  metric_name         = "CPUUtilization"
  namespace           = "AWS/ElastiCache"
  period              = "300"
  statistic           = "Average"
  threshold           = "75"
  alarm_description   = "This metric monitors Redis CPU utilization"
  alarm_actions       = [aws_sns_topic.alerts.arn]

  dimensions = {
    CacheClusterId = aws_elasticache_replication_group.redis.id
  }

  tags = {
    Name        = "ai-blockchain-analytics-redis-high-cpu-alarm"
    Project     = "AI-Blockchain-Analytics"
    Environment = var.environment
  }
}

# SNS Topic for Alerts
resource "aws_sns_topic" "alerts" {
  name = "ai-blockchain-analytics-alerts"

  tags = {
    Name        = "ai-blockchain-analytics-alerts"
    Project     = "AI-Blockchain-Analytics"
    Environment = var.environment
  }
}

resource "aws_sns_topic_subscription" "email_alerts" {
  count = var.alert_email != "" ? 1 : 0
  
  topic_arn = aws_sns_topic.alerts.arn
  protocol  = "email"
  endpoint  = var.alert_email
}

# SSM Parameters for Sentry and Telescope Configuration
resource "aws_ssm_parameter" "sentry_dsn" {
  name  = "/ai-blockchain-analytics/sentry-dsn"
  type  = "SecureString"
  value = var.sentry_dsn

  tags = {
    Name        = "ai-blockchain-analytics-sentry-dsn"
    Project     = "AI-Blockchain-Analytics"
    Environment = var.environment
  }
}

resource "aws_ssm_parameter" "telescope_allowed_ips" {
  name  = "/ai-blockchain-analytics/telescope-allowed-ips"
  type  = "String"
  value = var.telescope_allowed_ips

  tags = {
    Name        = "ai-blockchain-analytics-telescope-allowed-ips"
    Project     = "AI-Blockchain-Analytics"
    Environment = var.environment
  }
}

resource "aws_ssm_parameter" "telescope_admin_token" {
  name  = "/ai-blockchain-analytics/telescope-admin-token"
  type  = "SecureString"
  value = var.telescope_admin_token

  tags = {
    Name        = "ai-blockchain-analytics-telescope-admin-token"
    Project     = "AI-Blockchain-Analytics"
    Environment = var.environment
  }
}

# CloudWatch Dashboard for Monitoring
resource "aws_cloudwatch_dashboard" "main" {
  dashboard_name = "AI-Blockchain-Analytics-${var.environment}"

  dashboard_body = jsonencode({
    widgets = [
      {
        type   = "metric"
        x      = 0
        y      = 0
        width  = 12
        height = 6

        properties = {
          metrics = [
            ["AWS/ECS", "CPUUtilization", "ServiceName", aws_ecs_service.app.name, "ClusterName", aws_ecs_cluster.main.name],
            [".", "MemoryUtilization", ".", ".", ".", "."],
          ]
          period = 300
          stat   = "Average"
          region = var.aws_region
          title  = "ECS CPU and Memory Utilization"
        }
      },
      {
        type   = "metric"
        x      = 0
        y      = 6
        width  = 12
        height = 6

        properties = {
          metrics = [
            ["AWS/RDS", "CPUUtilization", "DBInstanceIdentifier", aws_db_instance.postgres.id],
            [".", "DatabaseConnections", ".", "."],
            [".", "ReadLatency", ".", "."],
            [".", "WriteLatency", ".", "."],
          ]
          period = 300
          stat   = "Average"
          region = var.aws_region
          title  = "RDS Performance Metrics"
        }
      },
      {
        type   = "metric"
        x      = 0
        y      = 12
        width  = 12
        height = 6

        properties = {
          metrics = [
            ["AWS/ElastiCache", "CPUUtilization", "CacheClusterId", aws_elasticache_replication_group.redis.id],
            [".", "CurrConnections", ".", "."],
            [".", "NetworkBytesIn", ".", "."],
            [".", "NetworkBytesOut", ".", "."],
          ]
          period = 300
          stat   = "Average"
          region = var.aws_region
          title  = "ElastiCache Redis Metrics"
        }
      }
    ]
  })

  tags = {
    Name        = "ai-blockchain-analytics-dashboard"
    Project     = "AI-Blockchain-Analytics"
    Environment = var.environment
  }
}