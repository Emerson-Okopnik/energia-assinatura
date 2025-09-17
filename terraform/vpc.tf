# vpc.tf

// Defina a Nuvem Privada Virtual (VPC) e o Gateway de Internet. A VPC é
// configurada com suporte a DNS e nomes de host habilitados para suportar serviços
// como EC2 e RDS. O Gateway de Internet se conecta à VPC para
// fornecer conectividade de saída para recursos na sub-rede pública e para
// o gateway NAT que atende à sub-rede privada.

resource "aws_vpc" "this" {
  cidr_block           = var.vpc_cidr
  enable_dns_support   = true
  enable_dns_hostnames = true

  tags = merge(local.default_tags, {
    Name = "${local.name_prefix}-vpc"
  })
}

resource "aws_internet_gateway" "this" {
  vpc_id = aws_vpc.this.id

  tags = merge(local.default_tags, {
    Name = "${local.name_prefix}-igw"
  })
}
