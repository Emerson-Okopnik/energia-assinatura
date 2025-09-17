# outputs.tf

// As saídas fornecem informações úteis após a criação da infraestrutura.
// Você pode usar esses valores para acessar o bastion por SSH, conectar-se do
// bastion ao aplicativo e ao banco de dados e verificar os IDs dos recursos.

output "vpc_id" {
  description = "ID of the VPC"
  value       = aws_vpc.this.id
}

output "public_subnet_id" {
  description = "ID of the public subnet"
  value       = aws_subnet.public.id
}

output "private_subnet_id" {
  description = "ID of the private subnet"
  value       = aws_subnet.private.id
}

output "bastion_public_ip" {
  description = "Public IP address of the bastion host"
  value       = aws_instance.bastion.public_ip
}

output "app_private_ip" {
  description = "Private IP address of the application host"
  value       = aws_instance.app.private_ip
}

output "db_endpoint" {
  description = "Connection endpoint for the RDS instance"
  value       = aws_db_instance.this.endpoint
}
