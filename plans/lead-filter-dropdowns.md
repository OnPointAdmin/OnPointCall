# Lead table filters: same dropdown everywhere

Clicking the **filter icon** on All Leads, calling-list Leads, Qualify/Import batch Leads, and Callbacks opens the **same filters**, stacked **vertically**.

**Qualify Leads and Assign Leads** are the exception: the same filter set is **page chrome** above the Assign/Qualify form (grouped sections; Selection expanded, the rest collapsed). The table hides its filter UI so search and the column chooser stay in the toolbar.

## Change

In `app/Filament/Resources/Leads/Tables/LeadsTable.php`:

- Most presets: `->filters($filters, layout: FiltersLayout::Dropdown)` and `->filtersFormColumns(1)`
- Qualify / Assign: `FiltersLayout::AboveContent`, `deferFilters(false)`, and `LeadsTableFilters::filterFormSchema()`

`LeadsTableFilters::make()` keeps the full shared filter set on all screens. Qualify/Assign keep their default values (Holding + standard) only.

Only existing field exception: Calling List still hides **Calling list** because you are already on that list.
