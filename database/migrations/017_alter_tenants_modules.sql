-- Admin can override per-tenant modules independently of the plan.
-- NULL means "inherit from plan"; a JSON object means admin has set explicit values.
ALTER TABLE tenants
    ADD COLUMN modules_override JSONB DEFAULT NULL;
