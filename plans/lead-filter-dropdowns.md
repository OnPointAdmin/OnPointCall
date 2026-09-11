# Lead table filters: same dropdown everywhere

Clicking the **filter icon** on any lead list (All Leads, calling-list Leads, Qualify/Import batch Leads, Qualify Leads, Assign Leads, Callbacks) opens the **same filters**, stacked **vertically**. No Qualify/Assign-only modal, and no shorter batch-only filter list.

## Change

In `app/Filament/Resources/Leads/Tables/LeadsTable.php`, for every preset:

- `->filters($filters, layout: FiltersLayout::Dropdown)`
- `->filtersFormColumns(1)` so the popover stacks fields one under the next

`LeadsTableFilters::make()` keeps the full shared filter set on all screens. Qualify/Assign keep their default values (Holding + standard) only.

Only existing field exception: Calling List still hides **Calling list** because you are already on that list.
