# security_groups.tf

// Defina grupos de segurança que controlam o acesso à rede entre o bastion,
// a instância do aplicativo e o banco de dados RDS. As regras de entrada têm um escopo
// restrito às fontes necessárias. A saída é aberta para permitir a comunicação
// de saída (que será restrita pelas tabelas de rotas e pela configuração
// de NAT).

// Grupo de segurança para o host bastion. Permite SSH a partir de um
// CIDR configurável (geralmente o IP público da sua estação de trabalho local). A saída é permitida
// em todos os lugares para que o bastion possa acessar a Internet e a rede
// privada.
resource "aws_security_group" "bastion_sg" {
  name_prefix = "${local.name_prefix}-bastion-sg-"
  description = "Security group for bastion host"
  vpc_id      = aws_vpc.this.id

  ingress {
    description = "Allow SSH from allowed CIDR"
    from_port   = 22
    to_port     = 22
    protocol    = "tcp"
    cidr_blocks = [var.allowed_ssh_cidr]
  }

  egress {
    description = "Allow all outbound traffic"
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }

  tags = merge(local.default_tags, {
    Name = "${local.name_prefix}-bastion-sg"
  })
}

// Grupo de segurança para o host do aplicativo. Permite tráfego SSH apenas do
// grupo de segurança Bastion. Todas as saídas são permitidas para que o aplicativo possa
// se comunicar com a internet através do gateway NAT.
resource "aws_security_group" "app_sg" {
  name_prefix = "${local.name_prefix}-app-sg-"
  description = "Security group for application host"
  vpc_id      = aws_vpc.this.id

  ingress {
    description      = "Allow SSH from bastion"
    from_port        = 22
    to_port          = 22
    protocol         = "tcp"
    security_groups  = [aws_security_group.bastion_sg.id]
  }

  egress {
    description = "Allow all outbound traffic"
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }

  tags = merge(local.default_tags, {
    Name = "${local.name_prefix}-app-sg"
  })
}

// Grupo de segurança para o banco de dados RDS. Permite tráfego Postgres apenas do
// grupo de segurança do aplicativo. A saída é irrestrita para tráfego
// de retorno.
resource "aws_security_group" "rds_sg" {
  name_prefix = "${local.name_prefix}-rds-sg-"
  description = "Security group for RDS"
  vpc_id      = aws_vpc.this.id

  ingress {
    description     = "Allow Postgres from app"
    from_port       = 5432
    to_port         = 5432
    protocol        = "tcp"
    security_groups = [aws_security_group.app_sg.id]
  }

  egress {
    description = "Allow all outbound traffic"
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }

  tags = merge(local.default_tags, {
    Name = "${local.name_prefix}-rds-sg"
  })
}
