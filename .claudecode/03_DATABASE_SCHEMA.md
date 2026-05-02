# DATABASE SCHEMA

CREATE TABLE users (
  id UUID PRIMARY KEY,
  tenant_id UUID NOT NULL,
  email TEXT UNIQUE,
  password TEXT,
  first_name TEXT,
  last_name TEXT,
  role TEXT
);

CREATE TABLE medical_records (
  id UUID PRIMARY KEY,
  tenant_id UUID,
  child_id UUID,
  report TEXT NOT NULL
);
