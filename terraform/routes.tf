# routes.tf

// IP Elástico e Gateway NAT para a sub-rede privada. Um único gateway NAT
// é provisionado na sub-rede pública para permitir que instâncias na
// sub-rede privada acessem a Internet para atualizações ou comunicação
// de saída. O IP Elástico garante um endereço público estável para
// o gateway NAT. Ambos são marcados de forma consistente.

resource "aws_eip" "nat_eip" {
  domain = "vpc"

  tags = merge(local.default_tags, {
    Name = "${local.name_prefix}-nat-eip"
  })
}

resource "aws_nat_gateway" "this" {
  allocation_id = aws_eip.nat_eip.id
  subnet_id     = aws_subnet.public.id

  # Certifique-se de que o Gateway de Internet existe antes de criar o NAT
  depends_on = [aws_internet_gateway.this]

  tags = merge(local.default_tags, {
    Name = "${local.name_prefix}-nat"
  })
}

// Tabela de rotas pública: roteia o tráfego de saída da Internet através da Internet
// Gateway. Esta tabela está associada à sub-rede pública.
resource "aws_route_table" "public" {
  vpc_id = aws_vpc.this.id

  route {
    cidr_block = "0.0.0.0/0"
    gateway_id = aws_internet_gateway.this.id
  }

  tags = merge(local.default_tags, {
    Name = "${local.name_prefix}-public-rt"
  })
}

resource "aws_route_table_association" "public_assoc" {
  subnet_id      = aws_subnet.public.id
  route_table_id = aws_route_table.public.id
}

// Tabela de rotas privadas: roteia todo o tráfego de saída através do gateway NAT
// Esta tabela está associada à sub-rede privada.
resource "aws_route_table" "private" {
  vpc_id = aws_vpc.this.id

  route {
    cidr_block     = "0.0.0.0/0"
    nat_gateway_id = aws_nat_gateway.this.id
  }

  tags = merge(local.default_tags, {
    Name = "${local.name_prefix}-private-rt"
  })
}

resource "aws_route_table_association" "private_assoc" {
  subnet_id      = aws_subnet.private.id
  route_table_id = aws_route_table.private.id
}

resource "aws_route_table_association" "private_b_assoc" {
  subnet_id      = aws_subnet.private_b.id
  route_table_id = aws_route_table.private.id
}