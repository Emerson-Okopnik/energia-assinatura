# subnets.tf

// Crie uma sub-rede pública e uma privada dentro da única AZ definida em
// variáveis. A sub-rede pública mapeia IPs públicos na inicialização, permitindo que instâncias do EC2
// como o Bastion sejam acessadas diretamente pela Internet.
// A sub-rede privada não atribui IPs públicos, garantindo que a instância do aplicativo
// e o banco de dados RDS permaneçam privados. Ambas as sub-redes são marcadas
// apropriadamente.

resource "aws_subnet" "public" {
  vpc_id                  = aws_vpc.this.id
  cidr_block              = var.public_subnet_cidr
  availability_zone       = var.az
  map_public_ip_on_launch = true

  tags = merge(local.default_tags, {
    Name = "${local.name_prefix}-public-subnet"
  })
}

resource "aws_subnet" "private" {
  vpc_id                  = aws_vpc.this.id
  cidr_block              = var.private_subnet_cidr
  availability_zone       = var.az
  map_public_ip_on_launch = false

  tags = merge(local.default_tags, {
    Name = "${local.name_prefix}-private-subnet"
  })
}

resource "aws_subnet" "private_b" {
  vpc_id                  = aws_vpc.this.id
  cidr_block              = var.private_subnet_cidr_b
  availability_zone       = var.az_b
  map_public_ip_on_launch = false
  tags = merge(local.default_tags, {
    Name = "${local.name_prefix}-private-subnet-b"
  })
}
