-- The priority input moved from the page-bottom hooks to the in-form
-- `<entity>.modification.form-right.bottom` hooks: drop the old registrations,
-- they would render the block twice on installs that activated an older version.
DELETE mh FROM module_hook mh
INNER JOIN module m ON m.id = mh.module_id AND m.code = 'Sitemap'
INNER JOIN hook h ON h.id = mh.hook_id
WHERE h.code IN (
    'product-edit.bottom',
    'category-edit.bottom',
    'content-edit.bottom',
    'folder-edit.bottom',
    'brand-edit.bottom'
);
