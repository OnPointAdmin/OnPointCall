INSERT INTO lead_types (company_id, slug, name, active, created_at, updated_at)
SELECT id, 'standard', 'Standard', true, NOW(), NOW() FROM companies
ON CONFLICT DO NOTHING;

INSERT INTO lead_types (company_id, slug, name, active, created_at, updated_at)
SELECT id, 'tnb', 'TNB', true, NOW(), NOW() FROM companies
ON CONFLICT DO NOTHING;
