# rds.tf

// Defina o grupo de sub-redes do RDS e a instância do banco de dados. O banco de dados é
// colocado inteiramente na sub-rede privada, não é acessível publicamente e
// usa criptografia em repouso. Para fins de desenvolvimento e teste,
// a deletion_protection é desabilitada e os snapshots finais são ignorados. Ajuste
// estas configurações para uso em produção.

resource "aws_db_subnet_group" "this" {
  name       = "${local.name_prefix}-db-subnet-group"
  subnet_ids = [aws_subnet.private.id, aws_subnet.private_b.id]

  tags = merge(local.default_tags, {
    Name = "${local.name_prefix}-db-subnet-group"
  })
}

resource "aws_db_instance" "this" {
  identifier               = "${local.name_prefix}-db"
  engine                   = var.db_engine
  engine_version           = var.db_engine_version
  instance_class           = var.db_instance_class
  allocated_storage        = var.db_allocated_storage
  db_name                     = var.db_name
  username                 = var.db_username
  password                 = var.db_password
  db_subnet_group_name     = aws_db_subnet_group.this.name
  vpc_security_group_ids   = [aws_security_group.rds_sg.id]
  availability_zone        = var.az
  multi_az                 = false
  publicly_accessible      = false
  deletion_protection      = false
  skip_final_snapshot      = true
  storage_encrypted        = true
  backup_retention_period  = 0
  apply_immediately        = true

  tags = merge(local.default_tags, {
    Name = "${local.name_prefix}-db"
  })
}