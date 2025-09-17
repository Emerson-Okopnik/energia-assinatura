# ec2.tf

// Este arquivo define o par de chaves SSH e as instâncias do EC2. Duas instâncias
// são criadas: um bastion host público e um host de aplicativo privado. Uma
// fonte de dados é usada para consultar a AMI mais recente do Amazon Linux para a
// região escolhida. Ambas as instâncias usam o mesmo par de chaves fornecido pela
// variável.

// Consulta a AMI mais recente do Ubuntu 22.04 (Jammy Jellyfish) para a região atual.
data "aws_ami" "ubuntu" {
  most_recent = true
  owners      = ["099720109477"] # ID do proprietário Canonical

  filter {
    name   = "name"
    values = ["ubuntu/images/hvm-ssd/ubuntu-jammy-22.04-amd64-server-*"]
  }

  filter {
    name   = "architecture"
    values = ["x86_64"]
  }

  filter {
    name   = "root-device-type"
    values = ["ebs"]
  }

  filter {
    name   = "virtualization-type"
    values = ["hvm"]
  }
}

// Par de chaves SSH usando a chave pública fornecida. O nome da chave será
// derivado do nome do projeto para evitar colisões. O par de chaves
// armazena apenas a chave pública; a chave privada permanece com você.
resource "aws_key_pair" "this" {
  key_name   = "${local.name_prefix}-key"
  public_key = var.ssh_public_key

  tags = merge(local.default_tags, {
    Name = "${local.name_prefix}-key"
  })
}

// Host Bastion na sub-rede pública. Esta instância tem um IP público e
// fornece acesso SSH à rede privada. Use a variável allowed_ssh_cidr
// para restringir o acesso. Nenhum user_data é especificado para o
// bastion; é uma simples jumpbox.
resource "aws_instance" "bastion" {
  ami                    = data.aws_ami.ubuntu.id
  instance_type          = var.instance_type_bastion
  subnet_id              = aws_subnet.public.id
  vpc_security_group_ids = [aws_security_group.bastion_sg.id]
  key_name               = aws_key_pair.this.key_name
  associate_public_ip_address = true

  tags = merge(local.default_tags, {
    Name = "${local.name_prefix}-bastion"
  })
}

// Host do aplicativo na sub-rede privada. Esta instância não possui um
// IP público e deve ser acessada via bastion. Um script user_data
// simples atualiza o sistema e instala o utilitário `htop` como prova
// de conectividade. O tráfego de saída passa pelo gateway NAT.
resource "aws_instance" "app" {
  ami                    = data.aws_ami.ubuntu.id
  instance_type          = var.instance_type_app
  subnet_id              = aws_subnet.private.id
  vpc_security_group_ids = [aws_security_group.app_sg.id]
  key_name               = aws_key_pair.this.key_name
  associate_public_ip_address = false

  user_data = <<-EOF
    #!/bin/bash
    set -e
    # Update the package index and install basic utilities
    if command -v yum >/dev/null 2>&1; then
      yum update -y
      yum install -y htop
    elif command -v apt-get >/dev/null 2>&1; then
      apt-get update -y
      apt-get install -y htop
    fi
  EOF

  tags = merge(local.default_tags, {
    Name = "${local.name_prefix}-app"
  })
}
