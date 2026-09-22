# ARCHITECTURE

## Pattern
Clean Architecture (simplified DDD)

## Layers
- API
- Application
- Domain
- Infrastructure

## Multi-Tenant
- tenant = family
- tenant_id everywhere

## Optional Infrastructure
- Redis (REDIS_HOST empty -> no rate limiting)
- MinIO (MINIO_ENDPOINT empty -> local disk storage)
