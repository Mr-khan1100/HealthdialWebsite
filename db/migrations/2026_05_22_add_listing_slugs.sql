ALTER TABLE listings
  ADD COLUMN slug VARCHAR(220) NULL AFTER name,
  ADD COLUMN category_slug VARCHAR(100) NULL AFTER category_id,
  ADD COLUMN city_slug VARCHAR(120) NULL AFTER city,
  ADD UNIQUE KEY uq_listings_slug (slug),
  ADD KEY idx_listings_city_slug (city_slug),
  ADD KEY idx_listings_category_slug (category_slug);
