# ECS Cluster
resource "aws_ecs_cluster" "main" {
  name = "ai-blockchain-cluster"

  configuration {
    execute_command_configuration {
      logging = "OVERRIDE"
      log_configuration {
        cloud_watch_log_group_name = aws_cloudwatch_log_group.ecs_exec.name
      }
    }
  }

  setting {
    name  = "containerInsights"
    value = "enabled"
  }

  tags = {
    Name        = "ai-blockchain-cluster"
    Project     = "AI-Blockchain-Analytics"
    Environment = var.environment
  }
}

resource "aws_ecs_cluster_capacity_providers" "main" {
  cluster_name = aws_ecs_cluster.main.name

  capacity_providers = ["FARGATE", "FARGATE_SPOT"]

  default_capacity_provider_strategy {
    base              = 1
    weight            = 100
    capacity_provider = "FARGATE"
  }
}

# CloudWatch Log Groups
resource "aws_cloudwatch_log_group" "ecs_exec" {
  name              = "/aws/ecs/ai-blockchain-exec"
  retention_in_days = 7
}

resource "aws_cloudwatch_log_group" "roadrunner_app" {
  name              = "/ecs/ai-blockchain-roadrunner-app"
  retention_in_days = 14
}

resource "aws_cloudwatch_log_group" "horizon_worker" {
  name              = "/ecs/ai-blockchain-horizon-worker"
  retention_in_days = 14
}

# Application Load Balancer
resource "aws_lb" "main" {
  name               = "ai-blockchain-alb"
  internal           = false
  load_balancer_type = "application"
  security_groups    = [aws_security_group.alb.id]
  subnets            = aws_subnet.public[*].id

  enable_deletion_protection = var.environment == "production"

  tags = {
    Name        = "ai-blockchain-alb"
    Project     = "AI-Blockchain-Analytics"
    Environment = var.environment
  }
}

resource "aws_lb_target_group" "app" {
  name        = "ai-blockchain-app-tg"
  port        = 8000
  protocol    = "HTTP"
  target_type = "ip"
  vpc_id      = aws_vpc.main.id

  health_check {
    enabled             = true
    healthy_threshold   = 2
    interval            = 30
    matcher             = "200"
    path                = "/health"
    port                = "traffic-port"
    protocol            = "HTTP"
    timeout             = 5
    unhealthy_threshold = 5
  }

  tags = {
    Name        = "ai-blockchain-app-tg"
    Project     = "AI-Blockchain-Analytics"
    Environment = var.environment
  }
}

resource "aws_lb_target_group" "websocket" {
  name        = "ai-blockchain-ws-tg"
  port        = 6001
  protocol    = "HTTP"
  target_type = "ip"
  vpc_id      = aws_vpc.main.id

  health_check {
    enabled             = true
    healthy_threshold   = 2
    interval            = 30
    matcher             = "200,101"
    path                = "/"
    port                = "traffic-port"
    protocol            = "HTTP"
    timeout             = 5
    unhealthy_threshold = 5
  }

  tags = {
    Name        = "ai-blockchain-ws-tg"
    Project     = "AI-Blockchain-Analytics"
    Environment = var.environment
  }
}

resource "aws_lb_listener" "app" {
  load_balancer_arn = aws_lb.main.arn
  port              = "443"
  protocol          = "HTTPS"
  ssl_policy        = "ELBSecurityPolicy-TLS-1-2-2017-01"
  certificate_arn   = var.ssl_certificate_arn

  default_action {
    type             = "forward"
    target_group_arn = aws_lb_target_group.app.arn
  }
}

resource "aws_lb_listener" "websocket" {
  load_balancer_arn = aws_lb.main.arn
  port              = "6001"
  protocol          = "HTTPS"
  ssl_policy        = "ELBSecurityPolicy-TLS-1-2-2017-01"
  certificate_arn   = var.ssl_certificate_arn

  default_action {
    type             = "forward"
    target_group_arn = aws_lb_target_group.websocket.arn
  }
}

# Redirect HTTP to HTTPS
resource "aws_lb_listener" "redirect_http" {
  load_balancer_arn = aws_lb.main.arn
  port              = "80"
  protocol          = "HTTP"

  default_action {
    type = "redirect"

    redirect {
      port        = "443"
      protocol    = "HTTPS"
      status_code = "HTTP_301"
    }
  }
}

# ECS Task Definitions are managed externally via JSON files
# Services are defined below

# ECS Service for RoadRunner App
resource "aws_ecs_service" "roadrunner_app" {
  name            = "roadrunner-app"
  cluster         = aws_ecs_cluster.main.id
  task_definition = "ai-blockchain-roadrunner-app:${var.app_task_definition_revision}"
  desired_count   = var.app_desired_count

  capacity_provider_strategy {
    capacity_provider = "FARGATE"
    weight            = 100
  }

  network_configuration {
    security_groups  = [aws_security_group.ecs_tasks.id]
    subnets          = aws_subnet.private[*].id
    assign_public_ip = false
  }

  load_balancer {
    target_group_arn = aws_lb_target_group.app.arn
    container_name   = "roadrunner-app"
    container_port   = 8000
  }

  load_balancer {
    target_group_arn = aws_lb_target_group.websocket.arn
    container_name   = "roadrunner-app"
    container_port   = 6001
  }

  deployment_configuration {
    maximum_percent         = 200
    minimum_healthy_percent = 100

    deployment_circuit_breaker {
      enable   = true
      rollback = true
    }
  }

  enable_execute_command = true

  depends_on = [
    aws_lb_listener.app,
    aws_lb_listener.websocket
  ]

  tags = {
    Name        = "ai-blockchain-roadrunner-app"
    Project     = "AI-Blockchain-Analytics"
    Environment = var.environment
  }
}

# ECS Service for Horizon Worker
resource "aws_ecs_service" "horizon_worker" {
  name            = "horizon-worker"
  cluster         = aws_ecs_cluster.main.id
  task_definition = "ai-blockchain-horizon-worker:${var.worker_task_definition_revision}"
  desired_count   = var.worker_desired_count

  capacity_provider_strategy {
    capacity_provider = "FARGATE"
    weight            = 100
  }

  network_configuration {
    security_groups  = [aws_security_group.ecs_tasks.id]
    subnets          = aws_subnet.private[*].id
    assign_public_ip = false
  }

  deployment_configuration {
    maximum_percent         = 200
    minimum_healthy_percent = 50
  }

  enable_execute_command = true

  tags = {
    Name        = "ai-blockchain-horizon-worker"
    Project     = "AI-Blockchain-Analytics"
    Environment = var.environment
  }
}

# Auto Scaling for RoadRunner App
resource "aws_appautoscaling_target" "ecs_target_app" {
  max_capacity       = var.app_max_capacity
  min_capacity       = var.app_min_capacity
  resource_id        = "service/${aws_ecs_cluster.main.name}/${aws_ecs_service.roadrunner_app.name}"
  scalable_dimension = "ecs:service:DesiredCount"
  service_namespace  = "ecs"
}

resource "aws_appautoscaling_policy" "ecs_policy_cpu_app" {
  name               = "ai-blockchain-cpu-scaling-app"
  policy_type        = "TargetTrackingScaling"
  resource_id        = aws_appautoscaling_target.ecs_target_app.resource_id
  scalable_dimension = aws_appautoscaling_target.ecs_target_app.scalable_dimension
  service_namespace  = aws_appautoscaling_target.ecs_target_app.service_namespace

  target_tracking_scaling_policy_configuration {
    predefined_metric_specification {
      predefined_metric_type = "ECSServiceAverageCPUUtilization"
    }
    target_value       = 70.0
    scale_in_cooldown  = 300
    scale_out_cooldown = 60
  }
}

resource "aws_appautoscaling_policy" "ecs_policy_memory_app" {
  name               = "ai-blockchain-memory-scaling-app"
  policy_type        = "TargetTrackingScaling"
  resource_id        = aws_appautoscaling_target.ecs_target_app.resource_id
  scalable_dimension = aws_appautoscaling_target.ecs_target_app.scalable_dimension
  service_namespace  = aws_appautoscaling_target.ecs_target_app.service_namespace

  target_tracking_scaling_policy_configuration {
    predefined_metric_specification {
      predefined_metric_type = "ECSServiceAverageMemoryUtilization"
    }
    target_value       = 80.0
    scale_in_cooldown  = 300
    scale_out_cooldown = 60
  }
}

# Auto Scaling for Horizon Worker
resource "aws_appautoscaling_target" "ecs_target_worker" {
  max_capacity       = var.worker_max_capacity
  min_capacity       = var.worker_min_capacity
  resource_id        = "service/${aws_ecs_cluster.main.name}/${aws_ecs_service.horizon_worker.name}"
  scalable_dimension = "ecs:service:DesiredCount"
  service_namespace  = "ecs"
}

resource "aws_appautoscaling_policy" "ecs_policy_cpu_worker" {
  name               = "ai-blockchain-cpu-scaling-worker"
  policy_type        = "TargetTrackingScaling"
  resource_id        = aws_appautoscaling_target.ecs_target_worker.resource_id
  scalable_dimension = aws_appautoscaling_target.ecs_target_worker.scalable_dimension
  service_namespace  = aws_appautoscaling_target.ecs_target_worker.service_namespace

  target_tracking_scaling_policy_configuration {
    predefined_metric_specification {
      predefined_metric_type = "ECSServiceAverageCPUUtilization"
    }
    target_value       = 70.0
    scale_in_cooldown  = 300
    scale_out_cooldown = 60
  }
}