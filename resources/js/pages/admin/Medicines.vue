<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Beaker,
    FlaskConical,
    PackageCheck,
    PackageX,
    Pill,
    Sparkles,
    TriangleAlert,
} from '@lucide/vue';
import Avatar from 'primevue/avatar';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import MultiSelect from 'primevue/multiselect';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import { useConfirm } from 'primevue/useconfirm';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { dashboard } from '@/routes';
import { medicines as medicinesIndexRoute } from '@/routes/admin';
import categoriesRoute from '@/routes/admin/categories';
import medicinesRoutes from '@/routes/admin/medicines';
import sizesRoute from '@/routes/admin/sizes';

// Types
type Category = {
    id: number;
    name: string;
    stocks_count?: number;
};

type Size = {
    id: number;
    name: string;
    stocks_count?: number;
};

type Medicine = {
    id: number;
    name: string;
    category_id: number;
    size_id: number;
    quantity: number;
    category?: Category;
    size?: Size;
};

type GroupedMedicine = {
    id: number;
    name: string;
    total_quantity: number;
    variations: Medicine[];
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedMedicines = {
    data: GroupedMedicine[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    links: PaginationLink[];
    total: number;
};

type SummaryMetrics = {
    totalMedicines: number;
    totalVariations: number;
    totalUnits: number;
    inStockCount: number;
    lowStockCount: number;
    outOfStockCount: number;
};

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Medicines Inventory', href: medicinesIndexRoute() },
        ],
    },
});

const props = defineProps<{
    medicines: PaginatedMedicines;
    categories: Category[];
    sizes: Size[];
    summary?: SummaryMetrics;
    filters: {
        search: string | null;
        stock_status?: string | null;
        category_id?: number | null;
        size_id?: number | null;
    };
}>();

const mounted = ref(false);
onMounted(() => {
    mounted.value = true;
});

// Active main tab
const activeTab = ref<'ledger' | 'categories' | 'sizes'>('ledger');

// Filter state
const searchQuery = ref(props.filters.search ?? '');
const stockStatusFilter = ref(props.filters.stock_status ?? '');
const categoryFilter = ref<number | null>(
    props.filters.category_id ? Number(props.filters.category_id) : null,
);
const sizeFilter = ref<number | null>(
    props.filters.size_id ? Number(props.filters.size_id) : null,
);

const hasActiveFilters = computed(() => {
    return Boolean(
        searchQuery.value.trim() ||
            stockStatusFilter.value ||
            categoryFilter.value ||
            sizeFilter.value,
    );
});

// Inline Quantities map: maps medicine ID to its edited quantity
const editedQuantities = ref<Record<number, number>>({});
const tempQuantity = ref(0);

// Selected medicine name & variation ID for detailed split view
const selectedMedicineName = ref<string | null>(null);
const selectedVariationId = ref<number | null>(null);

const filteredMedicinesList = computed(() => props.medicines?.data ?? []);

const selectedGroupedMedicine = computed(() => {
    const list = filteredMedicinesList.value;
    if (list.length === 0) {
        return null;
    }

    if (selectedMedicineName.value === null) {
        return list[0];
    }

    return list.find((m) => m.name === selectedMedicineName.value) ?? list[0];
});

const selectedMedicine = computed<Medicine | null>(() => {
    const grouped = selectedGroupedMedicine.value;
    if (!grouped || !grouped.variations || grouped.variations.length === 0) {
        return null;
    }

    if (selectedVariationId.value === null) {
        return grouped.variations[0];
    }

    return (
        grouped.variations.find((v) => v.id === selectedVariationId.value) ??
        grouped.variations[0]
    );
});

const variationSearchQuery = ref('');

const filteredSelectedVariations = computed<Medicine[]>(() => {
    const variations = selectedGroupedMedicine.value?.variations ?? [];
    const q = variationSearchQuery.value.trim().toLowerCase();
    if (!q) {
        return variations;
    }

    return variations.filter((v) => {
        const cat = (v.category?.name ?? '').toLowerCase();
        const size = (v.size?.name ?? '').toLowerCase();
        const qty = String(v.quantity);
        return cat.includes(q) || size.includes(q) || qty.includes(q);
    });
});

const inventoryTabOptions = computed(() => [
    {
        label: 'Medicines Ledger',
        value: 'ledger',
        icon: 'pi pi-box',
        count: props.summary?.totalMedicines ?? props.medicines.total ?? 0,
    },
    {
        label: 'Potency / Categories',
        value: 'categories',
        icon: 'pi pi-sliders-h',
        count: props.categories.length,
    },
    {
        label: 'Container Sizes',
        value: 'sizes',
        icon: 'pi pi-sort-amount-up',
        count: props.sizes.length,
    },
]);

const standardPotencyPresets = [
    '6C',
    '12C',
    '30C',
    '200C',
    '1M',
    '10M',
    'CM',
    'Q',
    '3X',
    '6X',
    '12X',
];

const standardSizePresets = [
    '1 dram',
    '2 dram',
    '1/2 oz',
    '1 oz',
    '15ml',
    '30ml',
    '60ml',
    '100ml',
    '450ml',
];

// Initialize editedQuantities, tempQuantity, and default selection synchronously
if (props.medicines?.data) {
    props.medicines.data.forEach((grouped) => {
        if (grouped.variations) {
            grouped.variations.forEach((v) => {
                editedQuantities.value[v.id] = v.quantity;
            });
        }
    });

    if (props.medicines.data.length > 0) {
        selectedMedicineName.value = props.medicines.data[0].name;
        selectedVariationId.value =
            props.medicines.data[0].variations?.[0]?.id ?? null;
    }
}

if (selectedMedicine.value) {
    tempQuantity.value = selectedMedicine.value.quantity;
}

// Watchers for reactivity updates when props/selection changes
watch(
    () => props.medicines.data,
    (newData) => {
        if (newData) {
            newData.forEach((grouped) => {
                if (grouped.variations) {
                    grouped.variations.forEach((v) => {
                        editedQuantities.value[v.id] = v.quantity;
                    });
                }
            });

            if (newData.length > 0) {
                const stillExists = newData.some(
                    (m) => m.name === selectedMedicineName.value,
                );
                if (!stillExists) {
                    selectedMedicineName.value = newData[0].name;
                    selectedVariationId.value =
                        newData[0].variations?.[0]?.id ?? null;
                } else {
                    const grouped = newData.find(
                        (m) => m.name === selectedMedicineName.value,
                    );
                    const varExists = grouped?.variations?.some(
                        (v) => v.id === selectedVariationId.value,
                    );
                    if (!varExists) {
                        selectedVariationId.value =
                            grouped?.variations?.[0]?.id ?? null;
                    }
                }
            } else {
                selectedMedicineName.value = null;
                selectedVariationId.value = null;
            }
        } else {
            selectedMedicineName.value = null;
            selectedVariationId.value = null;
        }
    },
    { deep: true },
);

watch(selectedMedicine, (newMed) => {
    if (newMed) {
        tempQuantity.value = newMed.quantity;
    }
});

let searchDebounceTimer: ReturnType<typeof setTimeout> | null = null;

watch(searchQuery, () => {
    if (searchDebounceTimer) {
        clearTimeout(searchDebounceTimer);
    }
    searchDebounceTimer = setTimeout(() => {
        applyQuery();
    }, 300);
});

onBeforeUnmount(() => {
    if (searchDebounceTimer) {
        clearTimeout(searchDebounceTimer);
    }
});

function applyQuery(): void {
    router.get(
        medicinesIndexRoute().url,
        {
            search: searchQuery.value.trim() || undefined,
            stock_status: stockStatusFilter.value || undefined,
            category_id: categoryFilter.value || undefined,
            size_id: sizeFilter.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

function handleSearch(): void {
    if (searchDebounceTimer) {
        clearTimeout(searchDebounceTimer);
    }
    applyQuery();
}

function handleClearSearch(): void {
    searchQuery.value = '';
    handleSearch();
}

function selectStockStatus(status: string): void {
    activeTab.value = 'ledger';
    stockStatusFilter.value =
        stockStatusFilter.value === status && status !== '' ? '' : status;
    applyQuery();
}

function clearFilters(): void {
    if (searchDebounceTimer) {
        clearTimeout(searchDebounceTimer);
    }
    searchQuery.value = '';
    stockStatusFilter.value = '';
    categoryFilter.value = null;
    sizeFilter.value = null;
    applyQuery();
}

function goToPage(url: string | null): void {
    if (!url) {
        return;
    }

    router.get(
        url,
        {},
        {
            preserveScroll: true,
            preserveState: true,
        },
    );
}

function selectGrouped(grouped: GroupedMedicine): void {
    selectedMedicineName.value = grouped.name;
    selectedVariationId.value = grouped.variations?.[0]?.id ?? null;
}

function selectGroupedAndVariation(
    grouped: GroupedMedicine,
    variation: Medicine,
): void {
    selectedMedicineName.value = grouped.name;
    selectedVariationId.value = variation.id;
}

function selectVariation(variation: Medicine): void {
    selectedVariationId.value = variation.id;
}

function stepTempQuantity(delta: number): void {
    tempQuantity.value = Math.max(0, (Number(tempQuantity.value) || 0) + delta);
}

// Update single medicine quantity
const updatingQuantityId = ref<number | null>(null);
function saveQuantity(medicineId: number, overrideQuantity?: number): void {
    const nextQty =
        overrideQuantity !== undefined ? overrideQuantity : tempQuantity.value;
    updatingQuantityId.value = medicineId;
    router.patch(
        medicinesRoutes.quantity.update(medicineId).url,
        {
            quantity: Math.max(0, nextQty),
        },
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                updatingQuantityId.value = null;
            },
        },
    );
}

function quickStepVariation(variation: Medicine, delta: number): void {
    const nextQty = Math.max(0, variation.quantity + delta);
    if (selectedMedicine.value?.id === variation.id) {
        tempQuantity.value = nextQty;
    }
    saveQuantity(variation.id, nextQty);
}

const confirm = useConfirm();

// Delete medicine variation
function deleteMedicine(medicine: Medicine): void {
    confirm.require({
        header: 'Delete Medicine Variation',
        message: `Are you sure you want to delete variation "${medicine.name} (${medicine.category?.name ?? 'N/A'} - ${medicine.size?.name ?? 'N/A'})"?`,
        icon: 'pi pi-exclamation-triangle',
        rejectProps: {
            label: 'Cancel',
            severity: 'secondary',
            outlined: true,
        },
        acceptProps: {
            label: 'Delete Variation',
            severity: 'danger',
        },
        accept: () => {
            router.delete(medicinesRoutes.destroy(medicine.id).url, {
                preserveScroll: true,
                preserveState: true,
            });
        },
    });
}

// New Medicine Modal state & form
const showNewMedicineModal = ref(false);
const newMedicineName = ref('');
const isLockedMedicineMode = ref(false);
const selectedCategoryIds = ref<number[]>([]);
const selectedSizeIds = ref<number[]>([]);
const permutationQuantities = ref<Record<string, number>>({});
const bulkDefaultQuantity = ref<number>(10);
const isSubmittingMedicine = ref(false);

const matchedExistingMedicine = computed<GroupedMedicine | null>(() => {
    const q = newMedicineName.value.trim().toLowerCase();
    if (!q) {
        return null;
    }
    return (
        filteredMedicinesList.value.find(
            (m) => m.name.trim().toLowerCase() === q,
        ) ?? null
    );
});

const existingVariationMap = computed<Record<string, Medicine>>(() => {
    const map: Record<string, Medicine> = {};
    const matched = matchedExistingMedicine.value;
    if (!matched || !matched.variations) {
        return map;
    }
    for (const v of matched.variations) {
        map[`${v.category_id}-${v.size_id}`] = v;
    }
    return map;
});

const permutations = computed(() => {
    if (
        selectedCategoryIds.value.length === 0 ||
        selectedSizeIds.value.length === 0
    ) {
        return [];
    }

    const result: Array<{
        category_id: number;
        category_name: string;
        size_id: number;
        size_name: string;
        key: string;
        existingQuantity: number | null;
    }> = [];

    for (const catId of selectedCategoryIds.value) {
        const cat = props.categories.find((c) => c.id === catId);

        for (const sizeId of selectedSizeIds.value) {
            const sz = props.sizes.find((s) => s.id === sizeId);
            const key = `${catId}-${sizeId}`;
            const existingVar = existingVariationMap.value[key];
            result.push({
                category_id: catId,
                category_name: cat ? cat.name : '',
                size_id: sizeId,
                size_name: sz ? sz.name : '',
                key,
                existingQuantity: existingVar ? existingVar.quantity : null,
            });
        }
    }

    return result;
});

// Watch permutations & matchedExistingMedicine to sync permutationQuantities
watch(
    [permutations, matchedExistingMedicine],
    ([newPerms]) => {
        newPerms.forEach((p) => {
            if (permutationQuantities.value[p.key] === undefined) {
                permutationQuantities.value[p.key] =
                    p.existingQuantity !== null
                        ? p.existingQuantity
                        : bulkDefaultQuantity.value;
            }
        });
    },
    { immediate: true },
);

// Watch modal visibility to reset inputs when closed
watch(showNewMedicineModal, (visible) => {
    if (!visible) {
        resetNewMedicineForm();
    }
});

function applyBulkQuantity(qty: number): void {
    bulkDefaultQuantity.value = qty;
    permutations.value.forEach((p) => {
        permutationQuantities.value[p.key] = qty;
    });
}

function submitNewMedicine(): void {
    if (!newMedicineName.value.trim() || permutations.value.length === 0) {
        return;
    }

    isSubmittingMedicine.value = true;

    const variations = permutations.value.map((p) => ({
        category_id: p.category_id,
        size_id: p.size_id,
        quantity: permutationQuantities.value[p.key] ?? 0,
    }));

    router.post(
        medicinesRoutes.store().url,
        {
            name: newMedicineName.value.trim(),
            variations,
        },
        {
            onSuccess: () => {
                showNewMedicineModal.value = false;
                resetNewMedicineForm();
            },
            onFinish: () => {
                isSubmittingMedicine.value = false;
            },
        },
    );
}

function resetNewMedicineForm(): void {
    newMedicineName.value = '';
    isLockedMedicineMode.value = false;
    selectedCategoryIds.value = [];
    selectedSizeIds.value = [];
    permutationQuantities.value = {};
    bulkDefaultQuantity.value = 10;
}

function openCreateMedicineModal(): void {
    resetNewMedicineForm();
    showNewMedicineModal.value = true;
}

function openAddVariation(medicineName?: string): void {
    resetNewMedicineForm();
    const targetName =
        medicineName ||
        selectedGroupedMedicine.value?.name ||
        selectedMedicine.value?.name ||
        '';
    newMedicineName.value = targetName;
    isLockedMedicineMode.value = Boolean(targetName);
    showNewMedicineModal.value = true;
}

// Category Management Form
const newCategoryName = ref('');
const categorySearch = ref('');
const isSubmittingCategory = ref(false);

const filteredCategories = computed(() => {
    const q = categorySearch.value.trim().toLowerCase();
    if (!q) {
        return props.categories;
    }
    return props.categories.filter((c) => c.name.toLowerCase().includes(q));
});

const availablePotencyPresets = computed(() => {
    const existing = new Set(
        props.categories.map((c) => c.name.trim().toUpperCase()),
    );
    return standardPotencyPresets.filter(
        (p) => !existing.has(p.toUpperCase()),
    );
});

function submitNewCategory(presetName?: string): void {
    const valueToSave = (presetName ?? newCategoryName.value).trim();
    if (!valueToSave) {
        return;
    }

    isSubmittingCategory.value = true;
    router.post(
        categoriesRoute.store().url,
        {
            name: valueToSave,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                if (!presetName) {
                    newCategoryName.value = '';
                }
            },
            onFinish: () => {
                isSubmittingCategory.value = false;
            },
        },
    );
}

function deleteCategory(category: Category): void {
    confirm.require({
        header: 'Delete Potency / Category',
        message: `Are you sure you want to delete category option "${category.name}"?`,
        icon: 'pi pi-exclamation-triangle',
        rejectProps: {
            label: 'Cancel',
            severity: 'secondary',
            outlined: true,
        },
        acceptProps: {
            label: 'Delete Category',
            severity: 'danger',
        },
        accept: () => {
            router.delete(categoriesRoute.destroy(category.id).url, {
                preserveScroll: true,
                preserveState: true,
            });
        },
    });
}

// Category Editing State
const editingCategoryId = ref<number | null>(null);
const editingCategoryName = ref('');

function startEditCategory(category: Category): void {
    editingCategoryId.value = category.id;
    editingCategoryName.value = category.name;
}

function saveCategoryEdit(categoryId: number): void {
    if (!editingCategoryName.value.trim()) {
        return;
    }
    router.patch(
        categoriesRoute.update(categoryId).url,
        {
            name: editingCategoryName.value.trim(),
        },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                editingCategoryId.value = null;
                editingCategoryName.value = '';
            },
        },
    );
}

// Size Management Form
const newSizeName = ref('');
const sizeSearch = ref('');
const isSubmittingSize = ref(false);

const filteredSizes = computed(() => {
    const q = sizeSearch.value.trim().toLowerCase();
    if (!q) {
        return props.sizes;
    }
    return props.sizes.filter((s) => s.name.toLowerCase().includes(q));
});

const availableSizePresets = computed(() => {
    const existing = new Set(
        props.sizes.map((s) => s.name.trim().toLowerCase()),
    );
    return standardSizePresets.filter((p) => !existing.has(p.toLowerCase()));
});

function submitNewSize(presetName?: string): void {
    const valueToSave = (presetName ?? newSizeName.value).trim();
    if (!valueToSave) {
        return;
    }

    isSubmittingSize.value = true;
    router.post(
        sizesRoute.store().url,
        {
            name: valueToSave,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                if (!presetName) {
                    newSizeName.value = '';
                }
            },
            onFinish: () => {
                isSubmittingSize.value = false;
            },
        },
    );
}

function deleteSize(size: Size): void {
    confirm.require({
        header: 'Delete Container Size',
        message: `Are you sure you want to delete container size "${size.name}"?`,
        icon: 'pi pi-exclamation-triangle',
        rejectProps: {
            label: 'Cancel',
            severity: 'secondary',
            outlined: true,
        },
        acceptProps: {
            label: 'Delete Size',
            severity: 'danger',
        },
        accept: () => {
            router.delete(sizesRoute.destroy(size.id).url, {
                preserveScroll: true,
                preserveState: true,
            });
        },
    });
}

// Size Editing State
const editingSizeId = ref<number | null>(null);
const editingSizeName = ref('');

function startEditSize(size: Size): void {
    editingSizeId.value = size.id;
    editingSizeName.value = size.name;
}

function saveSizeEdit(sizeId: number): void {
    if (!editingSizeName.value.trim()) {
        return;
    }
    router.patch(
        sizesRoute.update(sizeId).url,
        {
            name: editingSizeName.value.trim(),
        },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                editingSizeId.value = null;
                editingSizeName.value = '';
            },
        },
    );
}

// Initials helper
function getInitials(name: string): string {
    if (!name) {
        return 'ME';
    }

    const words = name.trim().split(/\s+/);

    if (words.length >= 2) {
        return (words[0][0] + words[1][0]).toUpperCase();
    }

    return name.slice(0, 2).toUpperCase();
}

function getStockStatusLabel(quantity: number): string {
    if (quantity === 0) {
        return 'Out of stock';
    }

    if (quantity < 10) {
        return 'Low stock';
    }

    return 'In stock';
}

function getStockStatusSeverity(
    quantity: number,
): 'danger' | 'warn' | 'success' {
    if (quantity === 0) {
        return 'danger';
    }

    if (quantity < 10) {
        return 'warn';
    }

    return 'success';
}

function getVariationChipClass(quantity: number): string {
    if (quantity <= 0) {
        return 'var-chip--out';
    }
    if (quantity < 10) {
        return 'var-chip--low';
    }
    return 'var-chip--ok';
}

function getLowOrOutSummary(grouped: GroupedMedicine): {
    low: number;
    out: number;
} {
    let low = 0;
    let out = 0;
    for (const v of grouped.variations ?? []) {
        if (v.quantity <= 0) {
            out++;
        } else if (v.quantity < 10) {
            low++;
        }
    }
    return { low, out };
}
</script>

<template>
    <div class="page-grid medicines-page">
        <Head title="Medicines Inventory" />

        <!-- Teleport Primary CTA into Admin Top Header -->
        <Teleport v-if="mounted" to="#admin-header-actions">
            <div class="header-cta-group">
                <Button
                    label="Potency &amp; Sizes"
                    icon="pi pi-sliders-h"
                    severity="secondary"
                    outlined
                    class="header-action-btn"
                    @click="
                        activeTab =
                            activeTab === 'categories' ? 'ledger' : 'categories'
                    "
                />
                <Button
                    label="New Medicine"
                    icon="pi pi-plus"
                    class="p-button-primary header-action-btn"
                    @click="openCreateMedicineModal"
                />
            </div>
        </Teleport>

        <!-- Interactive Inventory KPI Summary Strip -->
        <section v-if="props.summary" class="inventory-stats-grid">
            <button
                type="button"
                class="page-card inventory-stat-card"
                :class="{
                    'is-active':
                        activeTab === 'ledger' && !stockStatusFilter,
                }"
                @click="selectStockStatus('')"
            >
                <div class="inventory-stat-icon icon-all">
                    <Pill :size="22" :stroke-width="2.1" />
                </div>
                <div class="inventory-stat-meta">
                    <span class="inventory-stat-label">Total Catalog</span>
                    <strong class="inventory-stat-value">
                        {{ props.summary.totalMedicines }}
                    </strong>
                    <span class="inventory-stat-hint">
                        {{ props.summary.totalVariations }} SKUs &bull;
                        {{ props.summary.totalUnits }} total units
                    </span>
                </div>
            </button>

            <button
                type="button"
                class="page-card inventory-stat-card"
                :class="{
                    'is-active':
                        activeTab === 'ledger' &&
                        stockStatusFilter === 'in_stock',
                }"
                @click="selectStockStatus('in_stock')"
            >
                <div class="inventory-stat-icon icon-available">
                    <PackageCheck :size="22" :stroke-width="2.1" />
                </div>
                <div class="inventory-stat-meta">
                    <span class="inventory-stat-label">Healthy Stock</span>
                    <strong class="inventory-stat-value">
                        {{ props.summary.inStockCount }}
                    </strong>
                    <span class="inventory-stat-hint">
                        Variations with 10+ units
                    </span>
                </div>
            </button>

            <button
                type="button"
                class="page-card inventory-stat-card"
                :class="{
                    'is-active':
                        activeTab === 'ledger' &&
                        stockStatusFilter === 'low_stock',
                }"
                @click="selectStockStatus('low_stock')"
            >
                <div class="inventory-stat-icon icon-low">
                    <TriangleAlert :size="22" :stroke-width="2.1" />
                </div>
                <div class="inventory-stat-meta">
                    <span class="inventory-stat-label">Low Stock Alert</span>
                    <strong class="inventory-stat-value">
                        {{ props.summary.lowStockCount }}
                    </strong>
                    <span class="inventory-stat-hint">
                        Variations with 1–9 units
                    </span>
                </div>
            </button>

            <button
                type="button"
                class="page-card inventory-stat-card"
                :class="{
                    'is-active':
                        activeTab === 'ledger' &&
                        stockStatusFilter === 'out_of_stock',
                }"
                @click="selectStockStatus('out_of_stock')"
            >
                <div class="inventory-stat-icon icon-out">
                    <PackageX :size="22" :stroke-width="2.1" />
                </div>
                <div class="inventory-stat-meta">
                    <span class="inventory-stat-label">Out of Stock</span>
                    <strong class="inventory-stat-value">
                        {{ props.summary.outOfStockCount }}
                    </strong>
                    <span class="inventory-stat-hint">
                        Depleted variations (0 units)
                    </span>
                </div>
            </button>
        </section>

        <!-- Unified Workspace Toolbar Card -->
        <div class="page-card workspace-nav-card">
            <div class="workspace-nav-bar">
                <div
                    class="workspace-tab-group"
                    role="tablist"
                    aria-label="Inventory sections"
                >
                    <button
                        v-for="tab in inventoryTabOptions"
                        :key="tab.value"
                        type="button"
                        role="tab"
                        :aria-selected="activeTab === tab.value"
                        class="workspace-tab-btn"
                        :class="{ 'is-active': activeTab === tab.value }"
                        @click="
                            activeTab = tab.value as
                                | 'ledger'
                                | 'categories'
                                | 'sizes'
                        "
                    >
                        <Pill
                            v-if="tab.value === 'ledger'"
                            :size="16"
                            :stroke-width="2.1"
                            class="workspace-tab-svg"
                        />
                        <FlaskConical
                            v-else-if="tab.value === 'categories'"
                            :size="16"
                            :stroke-width="2.1"
                            class="workspace-tab-svg"
                        />
                        <Beaker
                            v-else
                            :size="16"
                            :stroke-width="2.1"
                            class="workspace-tab-svg"
                        />
                        <span>{{ tab.label }}</span>
                        <span class="tab-count-pill">{{ tab.count }}</span>
                    </button>
                </div>

                <div
                    v-if="activeTab === 'ledger'"
                    class="workspace-nav-bar__right"
                >
                    <div class="stock-segment-pills">
                        <button
                            type="button"
                            class="segment-pill"
                            :class="{ 'is-active': !stockStatusFilter }"
                            @click="selectStockStatus('')"
                        >
                            All SKUs
                        </button>
                        <button
                            type="button"
                            class="segment-pill"
                            :class="{
                                'is-active': stockStatusFilter === 'in_stock',
                            }"
                            @click="selectStockStatus('in_stock')"
                        >
                            <span class="dot dot--green"></span>
                            In Stock
                        </button>
                        <button
                            type="button"
                            class="segment-pill"
                            :class="{
                                'is-active': stockStatusFilter === 'low_stock',
                            }"
                            @click="selectStockStatus('low_stock')"
                        >
                            <span class="dot dot--amber"></span>
                            Low Stock
                        </button>
                        <button
                            type="button"
                            class="segment-pill"
                            :class="{
                                'is-active':
                                    stockStatusFilter === 'out_of_stock',
                            }"
                            @click="selectStockStatus('out_of_stock')"
                        >
                            <span class="dot dot--red"></span>
                            Out of Stock
                        </button>
                    </div>
                </div>

                <div v-else class="workspace-nav-bar__right">
                    <Button
                        label="Back to Ledger"
                        icon="pi pi-arrow-left"
                        severity="secondary"
                        outlined
                        size="small"
                        class="flat-btn w-auto"
                        @click="activeTab = 'ledger'"
                    />
                </div>
            </div>

            <!-- Integrated Search & Filter Row for Ledger Tab -->
            <div v-if="activeTab === 'ledger'" class="ledger-filter-row">
                <div class="medicine-search-input-wrap">
                    <i class="pi pi-search medicine-search-icon"></i>
                    <InputText
                        v-model="searchQuery"
                        placeholder="Search medicine by name, potency (e.g. 200C), or size (e.g. 30ml)..."
                        class="medicine-search-input"
                        @keyup.enter="handleSearch"
                    />
                    <button
                        v-if="searchQuery"
                        type="button"
                        class="search-clear-icon-btn"
                        title="Clear search"
                        @click="handleClearSearch"
                    >
                        <i class="pi pi-times"></i>
                    </button>
                </div>

                <Select
                    v-model="categoryFilter"
                    :options="props.categories"
                    option-label="name"
                    option-value="id"
                    placeholder="All Potencies / Categories"
                    show-clear
                    class="filter-dropdown"
                    @change="applyQuery"
                />

                <Select
                    v-model="sizeFilter"
                    :options="props.sizes"
                    option-label="name"
                    option-value="id"
                    placeholder="All Container Sizes"
                    show-clear
                    class="filter-dropdown"
                    @change="applyQuery"
                />

                <Button
                    v-if="hasActiveFilters"
                    label="Reset"
                    icon="pi pi-filter-slash"
                    severity="secondary"
                    outlined
                    size="small"
                    class="flat-btn w-auto shrink-0"
                    @click="clearFilters"
                />
            </div>
        </div>

        <!-- TAB 1: Ledger Tab View (Split View) -->
        <div v-if="activeTab === 'ledger'" class="tab-pane-container">
            <div class="medicines-split-view">
                <!-- Left Pane: Medicines registry -->
                <article
                    class="page-card admin-table-card medicines-list-pane"
                >
                    <header class="pane-sub-toolbar">
                        <div class="pane-sub-toolbar__left">
                            <span class="pane-sub-toolbar__icon">
                                <Pill :size="16" :stroke-width="2.1" />
                            </span>
                            <span class="pane-sub-toolbar__title">
                                Medicines Registry
                            </span>
                            <span class="pane-sub-toolbar__count">
                                {{ props.medicines.total }} medicine group(s)
                            </span>
                        </div>
                        <Button
                            label="New Medicine"
                            icon="pi pi-plus"
                            size="small"
                            class="p-button-primary flat-btn w-auto"
                            @click="openCreateMedicineModal"
                        />
                    </header>

                    <DataTable
                        v-if="filteredMedicinesList.length"
                        :value="filteredMedicinesList"
                        responsive-layout="scroll"
                        row-hover
                        class="admin-table medicines-table"
                        :row-class="
                            (data) => ({
                                'is-selected':
                                    selectedGroupedMedicine?.name === data.name,
                            })
                        "
                        @row-click="(e) => selectGrouped(e.data)"
                    >
                        <Column header="Medicine" style="min-width: 13rem">
                            <template #body="{ data }">
                                <div class="medicine-info-cell">
                                    <Avatar
                                        :label="getInitials(data.name)"
                                        shape="circle"
                                        class="admin-person-cell__avatar medicine-row-avatar"
                                    />
                                    <div class="medicine-info-copy">
                                        <div
                                            class="medicine-title medicine-title--strong"
                                        >
                                            {{ data.name }}
                                        </div>
                                        <div class="medicine-meta">
                                            <span>
                                                {{
                                                    data.variations?.length ??
                                                    0
                                                }}
                                                SKU(s)
                                            </span>
                                            <span class="medicine-meta__dot"
                                                >&middot;</span
                                            >
                                            <span>
                                                {{ data.total_quantity }} units
                                                total
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </Column>

                        <Column
                            header="Potency &amp; Size Variations"
                            style="min-width: 15.5rem"
                        >
                            <template #body="{ data }">
                                <div class="inline-variations-wrap">
                                    <button
                                        v-for="v in (data.variations ?? []).slice(
                                            0,
                                            5,
                                        )"
                                        :key="v.id"
                                        type="button"
                                        class="var-chip"
                                        :class="[
                                            getVariationChipClass(v.quantity),
                                            {
                                                'var-chip--selected':
                                                    selectedMedicine?.id ===
                                                    v.id,
                                            },
                                        ]"
                                        :title="`Click to inspect ${v.category?.name ?? ''} · ${v.size?.name ?? ''} (Qty: ${v.quantity})`"
                                        @click.stop="
                                            selectGroupedAndVariation(data, v)
                                        "
                                    >
                                        <span class="var-chip__label">
                                            {{ v.category?.name ?? 'N/A' }}
                                            &middot;
                                            {{ v.size?.name ?? 'N/A' }}
                                        </span>
                                        <strong class="var-chip__qty">
                                            {{ v.quantity }}
                                        </strong>
                                    </button>
                                    <span
                                        v-if="
                                            (data.variations?.length ?? 0) > 5
                                        "
                                        class="var-chip var-chip--more"
                                    >
                                        +{{ data.variations.length - 5 }} more
                                    </span>
                                </div>
                            </template>
                        </Column>

                        <Column
                            header="Stock Status"
                            style="width: 9.5rem; min-width: 9.5rem"
                        >
                            <template #body="{ data }">
                                <div class="stock-status-cell">
                                    <Tag
                                        :value="
                                            getStockStatusLabel(
                                                data.total_quantity,
                                            )
                                        "
                                        :severity="
                                            getStockStatusSeverity(
                                                data.total_quantity,
                                            )
                                        "
                                        rounded
                                        class="stock-tag-nowrap"
                                    />
                                    <span
                                        v-if="
                                            getLowOrOutSummary(data).out > 0 ||
                                            getLowOrOutSummary(data).low > 0
                                        "
                                        class="stock-sub-alert"
                                    >
                                        <span
                                            class="dot"
                                            :class="
                                                getLowOrOutSummary(data).out > 0
                                                    ? 'dot--red'
                                                    : 'dot--amber'
                                            "
                                        ></span>
                                        <template
                                            v-if="
                                                getLowOrOutSummary(data).out > 0
                                            "
                                        >
                                            {{ getLowOrOutSummary(data).out }}
                                            empty
                                        </template>
                                        <template
                                            v-if="
                                                getLowOrOutSummary(data).out >
                                                    0 &&
                                                getLowOrOutSummary(data).low > 0
                                            "
                                        >
                                            &middot;
                                        </template>
                                        <template
                                            v-if="
                                                getLowOrOutSummary(data).low > 0
                                            "
                                        >
                                            {{ getLowOrOutSummary(data).low }}
                                            low
                                        </template>
                                    </span>
                                </div>
                            </template>
                        </Column>

                        <Column
                            header="Actions"
                            class="text-right"
                            header-class="text-right"
                            style="width: 6rem; min-width: 6rem"
                        >
                            <template #body="{ data }">
                                <div class="row-quick-actions">
                                    <Button
                                        v-tooltip.top="'Add Variation'"
                                        icon="pi pi-plus"
                                        severity="secondary"
                                        text
                                        rounded
                                        size="small"
                                        class="action-icon-btn"
                                        aria-label="Add Variation"
                                        @click.stop="
                                            openAddVariation(data.name)
                                        "
                                    />
                                    <span
                                        class="row-chevron"
                                        :class="{
                                            'is-active':
                                                selectedGroupedMedicine?.name ===
                                                data.name,
                                        }"
                                    >
                                        <i class="pi pi-chevron-right"></i>
                                    </span>
                                </div>
                            </template>
                        </Column>
                    </DataTable>

                    <div
                        v-else
                        class="admin-empty-state admin-empty-state--soft"
                    >
                        <div class="empty-state-icon-ring">
                            <Pill :size="28" :stroke-width="1.9" />
                        </div>
                        <h3>No medicines found</h3>
                        <p>
                            <template v-if="hasActiveFilters">
                                No medicine variations match your active search
                                or filter criteria.
                            </template>
                            <template v-else>
                                Get started by adding your first medicine to the
                                catalog.
                            </template>
                        </p>
                        <Button
                            v-if="hasActiveFilters"
                            label="Clear filters"
                            icon="pi pi-filter-slash"
                            severity="secondary"
                            outlined
                            size="small"
                            class="flat-btn w-auto medicines-empty-state__action"
                            @click="clearFilters"
                        />
                        <Button
                            v-else
                            label="New Medicine"
                            icon="pi pi-plus"
                            size="small"
                            class="p-button-primary flat-btn w-auto medicines-empty-state__action"
                            @click="openCreateMedicineModal"
                        />
                    </div>

                    <!-- Pagination navigation -->
                    <div
                        v-if="filteredMedicinesList.length"
                        class="admin-pagination-container"
                    >
                        <span class="medicine-pagination-summary">
                            Showing
                            <strong>{{ filteredMedicinesList.length }}</strong>
                            of <strong>{{ props.medicines.total }}</strong>
                        </span>

                        <div
                            v-if="
                                props.medicines.links &&
                                props.medicines.links.length > 3
                            "
                            class="admin-pagination"
                        >
                            <button
                                v-for="link in props.medicines.links"
                                :key="link.label"
                                type="button"
                                class="pagination-btn"
                                :class="{
                                    'is-active': link.active,
                                    'is-disabled': !link.url,
                                }"
                                :disabled="!link.url"
                                @click="goToPage(link.url)"
                                v-html="link.label"
                            />
                        </div>
                    </div>
                </article>

                <!-- Right Pane: Medicine Details & Quantity Editor -->
                <article class="page-card medicine-details-pane">
                    <div
                        v-if="selectedMedicine"
                        class="details-pane-container"
                    >
                        <header class="medicine-profile-header">
                            <div class="medicine-detail-identity">
                                <div class="medicine-detail-identity__left">
                                    <Avatar
                                        :label="
                                            getInitials(selectedMedicine.name)
                                        "
                                        shape="circle"
                                        class="medicine-profile-avatar"
                                    />
                                    <div class="medicine-detail-identity__copy">
                                        <span class="inspector-eyebrow">
                                            Selected Variation
                                        </span>
                                        <h3
                                            class="panel-title medicine-selected-title"
                                        >
                                            {{ selectedMedicine.name }}
                                        </h3>
                                    </div>
                                </div>
                                <div class="inspector-header-actions">
                                    <Button
                                        icon="pi pi-plus"
                                        label="Add SKU"
                                        severity="secondary"
                                        outlined
                                        size="small"
                                        class="flat-btn w-auto"
                                        @click="
                                            openAddVariation(
                                                selectedMedicine.name,
                                            )
                                        "
                                    />
                                    <Button
                                        v-tooltip.top="'Delete this variation'"
                                        icon="pi pi-trash"
                                        severity="danger"
                                        outlined
                                        size="small"
                                        class="action-icon-btn"
                                        aria-label="Delete this variation"
                                        @click="
                                            deleteMedicine(selectedMedicine)
                                        "
                                    />
                                </div>
                            </div>

                            <div class="medicine-selected-badges">
                                <span class="badge-tag font-mono">
                                    SKU #{{ selectedMedicine.id }}
                                </span>
                                <span class="badge-tag badge-tag--teal">
                                    <FlaskConical
                                        :size="13"
                                        :stroke-width="2.2"
                                    />
                                    Potency:
                                    {{
                                        selectedMedicine.category?.name ?? 'N/A'
                                    }}
                                </span>
                                <span class="badge-tag badge-tag--blue">
                                    <Beaker :size="13" :stroke-width="2.2" />
                                    Size:
                                    {{ selectedMedicine.size?.name ?? 'N/A' }}
                                </span>
                            </div>
                        </header>

                        <!-- Stock Control Card -->
                        <div class="inventory-card">
                            <div class="inventory-card__top">
                                <div>
                                    <span
                                        class="detail-label medicine-detail-label"
                                    >
                                        CURRENT VARIATION STOCK
                                    </span>
                                    <span class="current-stock-big">
                                        {{ selectedMedicine.quantity }}
                                        <small>units available</small>
                                    </span>
                                </div>
                                <Tag
                                    :value="
                                        getStockStatusLabel(
                                            selectedMedicine.quantity,
                                        )
                                    "
                                    :severity="
                                        getStockStatusSeverity(
                                            selectedMedicine.quantity,
                                        )
                                    "
                                    rounded
                                    class="stock-tag-nowrap"
                                />
                            </div>

                            <!-- 1-Click Quick Stepper Pills -->
                            <div class="quick-step-bar">
                                <span class="quick-step-bar__label">
                                    <Sparkles :size="13" :stroke-width="2.2" />
                                    Quick Adjust:
                                </span>
                                <div class="quick-step-pills">
                                    <button
                                        type="button"
                                        class="step-pill step-pill--minus"
                                        @click="stepTempQuantity(-10)"
                                    >
                                        -10
                                    </button>
                                    <button
                                        type="button"
                                        class="step-pill step-pill--minus"
                                        @click="stepTempQuantity(-5)"
                                    >
                                        -5
                                    </button>
                                    <button
                                        type="button"
                                        class="step-pill step-pill--minus"
                                        @click="stepTempQuantity(-1)"
                                    >
                                        -1
                                    </button>
                                    <button
                                        type="button"
                                        class="step-pill step-pill--plus"
                                        @click="stepTempQuantity(1)"
                                    >
                                        +1
                                    </button>
                                    <button
                                        type="button"
                                        class="step-pill step-pill--plus"
                                        @click="stepTempQuantity(5)"
                                    >
                                        +5
                                    </button>
                                    <button
                                        type="button"
                                        class="step-pill step-pill--plus"
                                        @click="stepTempQuantity(10)"
                                    >
                                        +10
                                    </button>
                                    <button
                                        type="button"
                                        class="step-pill step-pill--plus"
                                        @click="stepTempQuantity(25)"
                                    >
                                        +25
                                    </button>
                                </div>
                            </div>

                            <div class="field mb-1">
                                <label
                                    for="medicine-quantity"
                                    class="field-label-strong text-sm"
                                >
                                    Adjust Stock Level
                                </label>
                                <div class="qty-form">
                                    <div class="qty-stepper-row">
                                        <button
                                            type="button"
                                            class="qty-step-icon-btn"
                                            :disabled="tempQuantity <= 0"
                                            title="Decrease by 1"
                                            @click="stepTempQuantity(-1)"
                                        >
                                            <i class="pi pi-minus"></i>
                                        </button>
                                        <InputNumber
                                            v-model="tempQuantity"
                                            input-id="medicine-quantity"
                                            :min="0"
                                            fluid
                                            class="medicine-quantity-input"
                                        />
                                        <button
                                            type="button"
                                            class="qty-step-icon-btn qty-step-icon-btn--plus"
                                            title="Increase by 1"
                                            @click="stepTempQuantity(1)"
                                        >
                                            <i class="pi pi-plus"></i>
                                        </button>
                                    </div>
                                    <div
                                        v-if="
                                            tempQuantity !==
                                            selectedMedicine.quantity
                                        "
                                        class="qty-unsaved-actions"
                                    >
                                        <Button
                                            label="Update Stock"
                                            icon="pi pi-check"
                                            severity="success"
                                            class="flat-btn inventory-card__action"
                                            :loading="
                                                updatingQuantityId ===
                                                selectedMedicine.id
                                            "
                                            @click="
                                                saveQuantity(
                                                    selectedMedicine.id,
                                                )
                                            "
                                        />
                                        <Button
                                            label="Reset"
                                            icon="pi pi-refresh"
                                            severity="secondary"
                                            outlined
                                            class="flat-btn w-auto"
                                            @click="
                                                tempQuantity =
                                                    selectedMedicine.quantity
                                            "
                                        />
                                    </div>
                                    <span
                                        v-else
                                        class="qty-sync-status qty-sync-status--fresh"
                                    >
                                        <span class="dot dot--green"></span>
                                        Stock level is up to date
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Product Family variations -->
                        <div class="medicine-variations-section">
                            <div class="variation-toolbar">
                                <div>
                                    <h4 class="sub-title">
                                        All Family Variations
                                    </h4>
                                    <span class="family-total-sub">
                                        Total across family:
                                        <strong>
                                            {{
                                                selectedGroupedMedicine?.total_quantity ??
                                                0
                                            }}
                                            units
                                        </strong>
                                    </span>
                                </div>
                                <span class="badge-count">
                                    {{
                                        selectedGroupedMedicine?.variations
                                            ?.length ?? 0
                                    }}
                                    SKUs
                                </span>
                            </div>

                            <div
                                v-if="(selectedGroupedMedicine?.variations?.length ?? 0) > 1"
                                class="mb-2"
                            >
                                <InputText
                                    v-model="variationSearchQuery"
                                    placeholder="Filter variations by potency (e.g. 200C) or size (e.g. 30ml)..."
                                    fluid
                                />
                            </div>

                            <div class="variations-grid">
                                <div
                                    v-for="variation in filteredSelectedVariations"
                                    :key="variation.id"
                                    class="variation-card variation-card--interactive"
                                    :class="{
                                        'is-selected-card':
                                            selectedMedicine?.id ===
                                            variation.id,
                                    }"
                                    @click="selectVariation(variation)"
                                >
                                    <div class="variation-card-header">
                                        <div class="variation-card-main">
                                            <div
                                                class="font-bold variation-name"
                                            >
                                                <span
                                                    class="dot"
                                                    :class="
                                                        variation.quantity <= 0
                                                            ? 'dot--red'
                                                            : variation.quantity <
                                                                10
                                                              ? 'dot--amber'
                                                              : 'dot--green'
                                                    "
                                                ></span>
                                                <span>
                                                    {{
                                                        variation.category
                                                            ?.name ?? 'N/A'
                                                    }}
                                                    &middot;
                                                    {{
                                                        variation.size?.name ??
                                                        'N/A'
                                                    }}
                                                </span>
                                            </div>
                                            <div
                                                class="variation-quantity-copy"
                                            >
                                                <span>
                                                    Stock:
                                                    <strong>
                                                        {{ variation.quantity }}
                                                        units
                                                    </strong>
                                                </span>
                                                <span
                                                    v-if="
                                                        selectedMedicine?.id ===
                                                        variation.id
                                                    "
                                                    class="selected-indicator"
                                                >
                                                    Active
                                                </span>
                                            </div>
                                        </div>

                                        <div
                                            class="variation-inline-stepper"
                                            @click.stop
                                        >
                                            <button
                                                type="button"
                                                class="mini-step-btn"
                                                :disabled="
                                                    variation.quantity <= 0 ||
                                                    updatingQuantityId ===
                                                        variation.id
                                                "
                                                title="Decrease by 1"
                                                @click="
                                                    quickStepVariation(
                                                        variation,
                                                        -1,
                                                    )
                                                "
                                            >
                                                <i class="pi pi-minus"></i>
                                            </button>
                                            <span class="mini-step-qty">
                                                {{ variation.quantity }}
                                            </span>
                                            <button
                                                type="button"
                                                class="mini-step-btn mini-step-btn--plus"
                                                :disabled="
                                                    updatingQuantityId ===
                                                    variation.id
                                                "
                                                title="Increase by 1"
                                                @click="
                                                    quickStepVariation(
                                                        variation,
                                                        1,
                                                    )
                                                "
                                            >
                                                <i class="pi pi-plus"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Add variation dotted card -->
                                <button
                                    type="button"
                                    class="variation-card dotted-card variation-card--interactive"
                                    @click="openAddVariation()"
                                >
                                    <div
                                        class="variation-card-header variation-card-header--centered"
                                    >
                                        <i
                                            class="pi pi-plus variation-card-add-icon"
                                        ></i>
                                        <span class="variation-card-add-label">
                                            Add New Potency / Size Variation
                                        </span>
                                    </div>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div
                        v-else
                        class="empty-state selection-placeholder medicine-selection-empty-state"
                    >
                        <div class="empty-state-icon-ring">
                            <Pill :size="28" :stroke-width="1.9" />
                        </div>
                        <h3>No medicine selected</h3>
                        <p>
                            Click a medicine row in the ledger registry on the
                            left to see details and inventory controls.
                        </p>
                    </div>
                </article>
            </div>
        </div>

        <!-- TAB 2: Category Options Tab View -->
        <div v-if="activeTab === 'categories'" class="tab-pane-container">
            <div class="content-split">
                <!-- Add Category Form -->
                <article class="page-card config-form-card">
                    <div class="config-form-head">
                        <span class="config-form-icon">
                            <FlaskConical :size="20" :stroke-width="2.1" />
                        </span>
                        <div>
                            <h3 class="panel-title section-heading-tight">
                                Add Potency / Category
                            </h3>
                            <p class="panel-subtitle">
                                Create a potency or category option for medicine
                                SKUs.
                            </p>
                        </div>
                    </div>

                    <form
                        @submit.prevent="submitNewCategory()"
                        class="simple-stack mt-3"
                    >
                        <div class="field">
                            <label
                                for="categoryName"
                                class="field-label-strong"
                            >
                                Category / Potency Name
                            </label>
                            <InputText
                                id="categoryName"
                                v-model="newCategoryName"
                                placeholder="e.g., 30C, 200C, 1M, Q"
                                required
                            />
                        </div>
                        <Button
                            type="submit"
                            label="Add Category"
                            icon="pi pi-plus"
                            size="small"
                            class="p-button-primary flat-btn w-auto"
                            :loading="isSubmittingCategory"
                        />
                    </form>

                    <div
                        v-if="availablePotencyPresets.length > 0"
                        class="quick-presets-box"
                    >
                        <span class="quick-presets-box__label">
                            <Sparkles :size="13" :stroke-width="2.2" />
                            1-Click Standard Potencies:
                        </span>
                        <div class="quick-preset-chips">
                            <button
                                v-for="preset in availablePotencyPresets"
                                :key="preset"
                                type="button"
                                class="preset-chip"
                                :disabled="isSubmittingCategory"
                                @click="submitNewCategory(preset)"
                            >
                                + {{ preset }}
                            </button>
                        </div>
                    </div>
                </article>

                <!-- Category List -->
                <article class="page-card config-list-card">
                    <div class="config-list-header">
                        <div>
                            <h3 class="panel-title section-heading-tight">
                                Configured Categories ({{
                                    props.categories.length
                                }})
                            </h3>
                            <p class="panel-subtitle">
                                All available potency and category values.
                            </p>
                        </div>
                        <div class="config-search-wrap">
                            <i class="pi pi-search"></i>
                            <InputText
                                v-model="categorySearch"
                                placeholder="Filter categories..."
                                size="small"
                            />
                        </div>
                    </div>

                    <div v-if="filteredCategories.length" class="config-grid">
                        <div
                            v-for="category in filteredCategories"
                            :key="category.id"
                            class="config-item-card"
                        >
                            <template v-if="editingCategoryId === category.id">
                                <div class="config-edit-row">
                                    <InputText
                                        v-model="editingCategoryName"
                                        size="small"
                                        class="config-edit-input"
                                        @keyup.enter="
                                            saveCategoryEdit(category.id)
                                        "
                                    />
                                    <Button
                                        icon="pi pi-check"
                                        severity="success"
                                        text
                                        rounded
                                        size="small"
                                        class="action-icon-btn"
                                        title="Save"
                                        @click="saveCategoryEdit(category.id)"
                                    />
                                    <Button
                                        icon="pi pi-times"
                                        severity="secondary"
                                        text
                                        rounded
                                        size="small"
                                        class="action-icon-btn"
                                        title="Cancel"
                                        @click="editingCategoryId = null"
                                    />
                                </div>
                            </template>
                            <template v-else>
                                <div class="config-item-left">
                                    <span class="config-item-badge">
                                        <FlaskConical
                                            :size="16"
                                            :stroke-width="2.1"
                                        />
                                    </span>
                                    <div class="config-item-info">
                                        <span class="config-item-title">{{
                                            category.name
                                        }}</span>
                                        <span class="config-usage-pill">
                                            {{ category.stocks_count ?? 0 }}
                                            SKU(s)
                                        </span>
                                    </div>
                                </div>
                                <div class="config-item-actions">
                                    <Button
                                        v-tooltip.top="'Edit category'"
                                        icon="pi pi-pencil"
                                        severity="secondary"
                                        text
                                        rounded
                                        size="small"
                                        class="action-icon-btn"
                                        aria-label="Edit category"
                                        @click="startEditCategory(category)"
                                    />
                                    <Button
                                        v-tooltip.top="'Delete category'"
                                        icon="pi pi-trash"
                                        severity="danger"
                                        text
                                        rounded
                                        size="small"
                                        class="action-icon-btn"
                                        aria-label="Delete category"
                                        @click="deleteCategory(category)"
                                    />
                                </div>
                            </template>
                        </div>
                    </div>
                    <div v-else class="empty-state empty-state--compact">
                        <FlaskConical
                            :size="24"
                            :stroke-width="1.8"
                            class="empty-state--compact__icon"
                        />
                        <p class="text-secondary">No categories defined yet.</p>
                    </div>
                </article>
            </div>
        </div>

        <!-- TAB 3: Size Options Tab View -->
        <div v-if="activeTab === 'sizes'" class="tab-pane-container">
            <div class="content-split">
                <!-- Add Size Form -->
                <article class="page-card config-form-card">
                    <div class="config-form-head">
                        <span class="config-form-icon config-form-icon--blue">
                            <Beaker :size="20" :stroke-width="2.1" />
                        </span>
                        <div>
                            <h3 class="panel-title section-heading-tight">
                                Add Container Size
                            </h3>
                            <p class="panel-subtitle">
                                Create a container size or volume option.
                            </p>
                        </div>
                    </div>
                    <form
                        @submit.prevent="submitNewSize()"
                        class="simple-stack mt-3"
                    >
                        <div class="field">
                            <label for="sizeName" class="field-label-strong"
                                >Size Name</label
                            >
                            <InputText
                                id="sizeName"
                                v-model="newSizeName"
                                placeholder="e.g., 30ml, 100ml, 2 dram, 1/2 oz"
                                required
                            />
                        </div>
                        <Button
                            type="submit"
                            label="Add Size"
                            icon="pi pi-plus"
                            size="small"
                            class="p-button-primary flat-btn w-auto"
                            :loading="isSubmittingSize"
                        />
                    </form>

                    <div
                        v-if="availableSizePresets.length > 0"
                        class="quick-presets-box"
                    >
                        <span class="quick-presets-box__label">
                            <Sparkles :size="13" :stroke-width="2.2" />
                            1-Click Standard Container Sizes:
                        </span>
                        <div class="quick-preset-chips">
                            <button
                                v-for="preset in availableSizePresets"
                                :key="preset"
                                type="button"
                                class="preset-chip"
                                :disabled="isSubmittingSize"
                                @click="submitNewSize(preset)"
                            >
                                + {{ preset }}
                            </button>
                        </div>
                    </div>
                </article>

                <!-- Size List -->
                <article class="page-card config-list-card">
                    <div class="config-list-header">
                        <div>
                            <h3 class="panel-title section-heading-tight">
                                Configured Sizes ({{ props.sizes.length }})
                            </h3>
                            <p class="panel-subtitle">
                                All available container sizes.
                            </p>
                        </div>
                        <div class="config-search-wrap">
                            <i class="pi pi-search"></i>
                            <InputText
                                v-model="sizeSearch"
                                placeholder="Filter sizes..."
                                size="small"
                            />
                        </div>
                    </div>

                    <div v-if="filteredSizes.length" class="config-grid">
                        <div
                            v-for="size in filteredSizes"
                            :key="size.id"
                            class="config-item-card"
                        >
                            <template v-if="editingSizeId === size.id">
                                <div class="config-edit-row">
                                    <InputText
                                        v-model="editingSizeName"
                                        size="small"
                                        class="config-edit-input"
                                        @keyup.enter="saveSizeEdit(size.id)"
                                    />
                                    <Button
                                        icon="pi pi-check"
                                        severity="success"
                                        text
                                        rounded
                                        size="small"
                                        class="action-icon-btn"
                                        title="Save"
                                        @click="saveSizeEdit(size.id)"
                                    />
                                    <Button
                                        icon="pi pi-times"
                                        severity="secondary"
                                        text
                                        rounded
                                        size="small"
                                        class="action-icon-btn"
                                        title="Cancel"
                                        @click="editingSizeId = null"
                                    />
                                </div>
                            </template>
                            <template v-else>
                                <div class="config-item-left">
                                    <span
                                        class="config-item-badge config-item-badge--blue"
                                    >
                                        <Beaker :size="16" :stroke-width="2.1" />
                                    </span>
                                    <div class="config-item-info">
                                        <span class="config-item-title">{{
                                            size.name
                                        }}</span>
                                        <span class="config-usage-pill">
                                            {{ size.stocks_count ?? 0 }} SKU(s)
                                        </span>
                                    </div>
                                </div>
                                <div class="config-item-actions">
                                    <Button
                                        v-tooltip.top="'Edit size'"
                                        icon="pi pi-pencil"
                                        severity="secondary"
                                        text
                                        rounded
                                        size="small"
                                        class="action-icon-btn"
                                        aria-label="Edit size"
                                        @click="startEditSize(size)"
                                    />
                                    <Button
                                        v-tooltip.top="'Delete size'"
                                        icon="pi pi-trash"
                                        severity="danger"
                                        text
                                        rounded
                                        size="small"
                                        class="action-icon-btn"
                                        aria-label="Delete size"
                                        @click="deleteSize(size)"
                                    />
                                </div>
                            </template>
                        </div>
                    </div>
                    <div v-else class="empty-state empty-state--compact">
                        <Beaker
                            :size="24"
                            :stroke-width="1.8"
                            class="empty-state--compact__icon"
                        />
                        <p class="text-secondary">No sizes defined yet.</p>
                    </div>
                </article>
            </div>
        </div>

        <!-- Create / Add Medicine Variations Dialog -->
        <Dialog
            v-model:visible="showNewMedicineModal"
            :header="
                matchedExistingMedicine
                    ? 'Add Medicine Variations'
                    : 'New Medicine'
            "
            :modal="true"
            :draggable="false"
            dismissable-mask
            class="admin-dialog medicine-dialog"
            :style="{ width: '38rem', maxWidth: '95vw' }"
            :breakpoints="{ '640px': '96vw' }"
        >
            <form class="dialog-form" @submit.prevent="submitNewMedicine">
                <div class="dialog-body">
                    <!-- Medicine Name -->
                    <div class="field">
                        <label for="medName" class="form-label">
                            Medicine Name <span class="required">*</span>
                        </label>
                        <InputText
                            id="medName"
                            v-model="newMedicineName"
                            placeholder="Enter medicine name (e.g., Arnica Montana, Nux Vomica)..."
                            required
                            autofocus
                            fluid
                        />
                    </div>

                    <!-- Existing Medicine Banner (matches CourierParcels & MedicalCertificates) -->
                    <div
                        v-if="matchedExistingMedicine"
                        class="selected-med-banner"
                    >
                        <div class="selected-med-banner__left">
                            <Avatar
                                :label="
                                    getInitials(matchedExistingMedicine.name)
                                "
                                shape="circle"
                                class="selected-med-banner__avatar"
                            />
                            <div class="selected-med-banner__info">
                                <div class="selected-med-banner__title">
                                    <strong>{{
                                        matchedExistingMedicine.name
                                    }}</strong>
                                    <span class="med-catalog-badge">
                                        In Catalog
                                    </span>
                                </div>
                                <div class="selected-med-banner__meta">
                                    <span>
                                        {{
                                            matchedExistingMedicine.variations
                                                ?.length ?? 0
                                        }}
                                        existing SKU(s)
                                    </span>
                                    <span>&bull;</span>
                                    <span>
                                        {{
                                            matchedExistingMedicine.total_quantity
                                        }}
                                        total units in stock
                                    </span>
                                </div>
                            </div>
                        </div>
                        <Button
                            v-if="isLockedMedicineMode"
                            type="button"
                            label="Change"
                            icon="pi pi-refresh"
                            severity="secondary"
                            text
                            size="small"
                            class="btn-auto"
                            @click="
                                isLockedMedicineMode = false;
                                newMedicineName = '';
                            "
                        />
                    </div>

                    <!-- 2-Column Row: Potencies & Container Sizes -->
                    <div class="form-row">
                        <div class="field">
                            <div class="field-header-inline">
                                <label for="med-categories" class="form-label">
                                    Potencies / Categories
                                    <span class="required">*</span>
                                </label>
                                <button
                                    type="button"
                                    class="inline-link-btn"
                                    @click="
                                        showNewMedicineModal = false;
                                        activeTab = 'categories';
                                    "
                                >
                                    <i class="pi pi-plus"></i> New Potency
                                </button>
                            </div>
                            <MultiSelect
                                id="med-categories"
                                v-model="selectedCategoryIds"
                                :options="props.categories"
                                option-label="name"
                                option-value="id"
                                placeholder="Select potencies..."
                                filter
                                display="chip"
                                fluid
                            />
                        </div>

                        <div class="field">
                            <div class="field-header-inline">
                                <label for="med-sizes" class="form-label">
                                    Container Sizes
                                    <span class="required">*</span>
                                </label>
                                <button
                                    type="button"
                                    class="inline-link-btn"
                                    @click="
                                        showNewMedicineModal = false;
                                        activeTab = 'sizes';
                                    "
                                >
                                    <i class="pi pi-plus"></i> New Size
                                </button>
                            </div>
                            <MultiSelect
                                id="med-sizes"
                                v-model="selectedSizeIds"
                                :options="props.sizes"
                                option-label="name"
                                option-value="id"
                                placeholder="Select sizes..."
                                filter
                                display="chip"
                                fluid
                            />
                        </div>
                    </div>

                    <!-- Stock Quantities per Variation -->
                    <div
                        v-if="permutations.length > 0"
                        class="variations-group"
                    >
                        <div class="variations-group__header">
                            <span class="variations-group__title">
                                Stock Quantities ({{ permutations.length }})
                            </span>
                            <div class="qty-preset-strip">
                                <span class="qty-preset-strip__label"
                                    >Set all:</span
                                >
                                <button
                                    v-for="q in [5, 10, 20, 50, 100]"
                                    :key="q"
                                    type="button"
                                    class="qty-preset-btn"
                                    :class="{
                                        'is-active': bulkDefaultQuantity === q,
                                    }"
                                    @click="applyBulkQuantity(q)"
                                >
                                    {{ q }}
                                </button>
                            </div>
                        </div>

                        <div class="variations-group__list">
                            <div
                                v-for="p in permutations"
                                :key="p.key"
                                class="variation-stock-row"
                            >
                                <div class="variation-stock-row__left">
                                    <span class="variation-stock-row__combo">
                                        <span class="variation-potency">{{
                                            p.category_name
                                        }}</span>
                                        <span class="variation-sep"
                                            >&middot;</span
                                        >
                                        <span class="variation-size">{{
                                            p.size_name
                                        }}</span>
                                    </span>
                                    <span
                                        v-if="p.existingQuantity !== null"
                                        class="variation-existing-badge"
                                    >
                                        In stock: {{ p.existingQuantity }}
                                    </span>
                                </div>

                                <div class="variation-stock-row__right">
                                    <InputNumber
                                        v-model="permutationQuantities[p.key]"
                                        :min="0"
                                        show-buttons
                                        button-layout="horizontal"
                                        decrement-button-icon="pi pi-minus"
                                        increment-button-icon="pi pi-plus"
                                        fluid
                                        class="variation-qty-input"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer Actions -->
                <div class="dialog-footer">
                    <Button
                        type="button"
                        label="Cancel"
                        severity="secondary"
                        outlined
                        class="btn-auto"
                        @click="showNewMedicineModal = false"
                    />
                    <Button
                        type="submit"
                        :label="
                            permutations.length > 0
                                ? `Save ${permutations.length} Variation(s)`
                                : 'Save Medicine'
                        "
                        icon="pi pi-check"
                        severity="primary"
                        class="btn-auto"
                        :disabled="
                            !newMedicineName.trim() ||
                            permutations.length === 0
                        "
                        :loading="isSubmittingMedicine"
                    />
                </div>
            </form>
        </Dialog>
    </div>
</template>

<style scoped>
.medicines-page {
    display: flex;
    flex-direction: column;
    gap: 1.15rem;
}

.header-cta-group {
    display: flex;
    align-items: center;
    gap: 0.65rem;
}

.header-cta-group :deep(.p-button),
.header-action-btn,
.flat-btn.w-auto,
:deep(.flat-btn.w-auto) {
    width: auto !important;
    white-space: nowrap !important;
}

.action-icon-btn,
:deep(.action-icon-btn) {
    width: 2.1rem !important;
    height: 2.1rem !important;
    min-width: 2.1rem !important;
    padding: 0 !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    flex-shrink: 0 !important;
}

/* Inventory health overview cards */
.inventory-stats-grid {
    display: grid;
    gap: 0.9rem;
    grid-template-columns: repeat(4, minmax(0, 1fr));
}

.inventory-stat-card {
    display: flex;
    align-items: center;
    gap: 0.95rem;
    padding: 1.1rem 1.2rem;
    background: var(--surface-card);
    border: 1px solid var(--surface-border);
    box-shadow: var(--card-shadow);
    cursor: pointer;
    text-align: left;
    font-family: inherit;
    width: 100%;
    transition:
        border-color 0.15s ease,
        box-shadow 0.15s ease,
        transform 0.12s ease;
}

.inventory-stat-card:hover {
    border-color: color-mix(
        in srgb,
        var(--primary-color) 45%,
        var(--surface-border)
    );
    transform: translateY(-1px);
}

.inventory-stat-card.is-active {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 2px
        color-mix(in srgb, var(--primary-color) 18%, transparent);
}

.inventory-stat-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.9rem;
    height: 2.9rem;
    border-radius: 10px;
    flex-shrink: 0;
}

.inventory-stat-icon.icon-all {
    background: color-mix(
        in srgb,
        var(--primary-color) 12%,
        var(--surface-card)
    );
    color: var(--primary-color);
}

.inventory-stat-icon.icon-available {
    background: color-mix(in srgb, #10b981 14%, var(--surface-card));
    color: #059669;
}

.inventory-stat-icon.icon-low {
    background: color-mix(in srgb, #f59e0b 16%, var(--surface-card));
    color: #d97706;
}

.inventory-stat-icon.icon-out {
    background: color-mix(in srgb, #ef4444 14%, var(--surface-card));
    color: #dc2626;
}

.inventory-stat-meta {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.inventory-stat-label {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--text-color-secondary);
}

.inventory-stat-value {
    font-size: 1.45rem;
    font-weight: 800;
    color: var(--text-color);
    line-height: 1.2;
}

.inventory-stat-hint {
    font-size: 0.72rem;
    color: var(--text-color-secondary);
    margin-top: 0.1rem;
}

/* Workspace Navigation & Filter Bar */
.workspace-nav-card {
    padding: 0;
    overflow: hidden;
}

.workspace-nav-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.85rem 1.25rem;
    border-bottom: 1px solid var(--surface-border);
    flex-wrap: wrap;
}

.workspace-tab-group {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.28rem;
    border-radius: 10px;
    background: var(--surface-ground);
    border: 1px solid var(--surface-border);
    flex-wrap: wrap;
}

.workspace-tab-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.45rem 0.9rem;
    border-radius: 7px;
    border: 1px solid transparent;
    background: transparent;
    color: var(--text-color-secondary);
    font-family: inherit;
    font-size: 0.84rem;
    font-weight: 700;
    cursor: pointer;
    white-space: nowrap;
    transition: all 0.15s ease;
}

.workspace-tab-svg {
    flex-shrink: 0;
}

.workspace-tab-btn:hover {
    color: var(--text-color);
}

.workspace-tab-btn.is-active {
    background: var(--surface-card);
    color: var(--primary-color);
    border-color: color-mix(
        in srgb,
        var(--primary-color) 28%,
        var(--surface-border)
    );
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05);
}

.tab-count-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 1.4rem;
    height: 1.25rem;
    padding: 0 0.38rem;
    border-radius: 999px;
    font-size: 0.7rem;
    font-weight: 800;
    background: color-mix(in srgb, currentColor 12%, transparent);
}

.stock-segment-pills {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: wrap;
}

.segment-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.36rem 0.78rem;
    border-radius: 999px;
    border: 1px solid var(--surface-border);
    background: var(--surface-ground);
    color: var(--text-color-secondary);
    font-family: inherit;
    font-size: 0.76rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.15s ease;
}

.segment-pill:hover {
    border-color: var(--primary-color);
    color: var(--text-color);
}

.segment-pill.is-active {
    background: color-mix(
        in srgb,
        var(--primary-color) 12%,
        var(--surface-card)
    );
    border-color: var(--primary-color);
    color: var(--primary-color);
}

.dot {
    width: 0.48rem;
    height: 0.48rem;
    border-radius: 50%;
    display: inline-block;
    flex-shrink: 0;
}

.dot--green {
    background: #10b981;
}

.dot--amber {
    background: #f59e0b;
}

.dot--red {
    background: #ef4444;
}

.ledger-filter-row {
    display: grid;
    grid-template-columns: 1.8fr 1fr 1fr auto;
    gap: 0.75rem;
    align-items: center;
    padding: 0.85rem 1.25rem;
    background: color-mix(
        in srgb,
        var(--surface-ground) 55%,
        var(--surface-card)
    );
}

.medicine-search-input-wrap {
    position: relative;
    display: flex;
    align-items: center;
    width: 100%;
}

.medicine-search-icon {
    position: absolute;
    left: 0.85rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-color-secondary);
    font-size: 0.88rem;
    pointer-events: none;
    z-index: 1;
}

.medicine-search-input-wrap :deep(.p-inputtext),
.medicine-search-input {
    width: 100% !important;
    padding-left: 2.45rem !important;
    padding-right: 2.2rem !important;
}

.search-clear-icon-btn {
    position: absolute;
    right: 0.55rem;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    padding: 0;
    line-height: 1;
    color: var(--text-color-secondary);
    cursor: pointer;
    width: 1.65rem;
    height: 1.65rem;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.14s ease;
}

.search-clear-icon-btn:hover {
    background: var(--surface-hover);
    color: var(--text-color);
}

/* Split View Layout */
.medicines-split-view {
    display: grid;
    grid-template-columns: 1.35fr 1fr;
    gap: 1.25rem;
    align-items: start;
}

.medicines-list-pane,
.medicine-details-pane {
    padding: 0;
    overflow: hidden;
}

@media (min-width: 1025px) {
    .medicine-details-pane {
        position: sticky;
        top: 1.25rem;
    }
}

.pane-sub-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.85rem 1.25rem;
    border-bottom: 1px solid var(--surface-border);
}

.pane-sub-toolbar__left {
    display: flex;
    align-items: center;
    gap: 0.55rem;
}

.pane-sub-toolbar__icon {
    width: 2rem;
    height: 2rem;
    border-radius: 8px;
    background: color-mix(in srgb, var(--primary-color) 12%, transparent);
    color: var(--primary-color);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.pane-sub-toolbar__title {
    font-size: 0.95rem;
    font-weight: 800;
    color: var(--text-color);
}

.pane-sub-toolbar__count {
    font-size: 0.76rem;
    font-weight: 700;
    padding: 0.14rem 0.5rem;
    border-radius: 999px;
    background: var(--surface-ground);
    border: 1px solid var(--surface-border);
    color: var(--text-color-secondary);
}

/* Medicine Info & Variation Chips in Table */
.medicine-info-cell {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.medicine-row-avatar {
    width: 2.35rem !important;
    height: 2.35rem !important;
    font-size: 0.8rem !important;
    font-weight: 800 !important;
}

.medicine-title {
    font-size: 0.92rem;
    color: var(--text-color);
}

.medicine-title--strong,
.config-item-title {
    font-weight: 700;
}

.medicine-meta {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.75rem;
    font-weight: 500;
    color: var(--text-color-secondary);
    margin-top: 0.15rem;
    flex-wrap: wrap;
}

.inline-variations-wrap {
    display: flex;
    flex-wrap: wrap;
    gap: 0.38rem;
}

.var-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.22rem 0.28rem 0.22rem 0.55rem;
    border-radius: 7px;
    font-family: inherit;
    font-size: 0.73rem;
    border: 1px solid var(--surface-border);
    background: var(--surface-card);
    color: var(--text-color);
    cursor: pointer;
    transition: all 0.14s ease;
}

.var-chip__label {
    font-weight: 600;
}

.var-chip__qty {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 1.4rem;
    padding: 0.08rem 0.35rem;
    border-radius: 5px;
    font-size: 0.7rem;
    font-weight: 800;
    background: var(--surface-ground);
}

.var-chip:hover {
    border-color: var(--primary-color);
}

.var-chip--selected {
    box-shadow: 0 0 0 1.5px var(--primary-color);
    border-color: var(--primary-color) !important;
}

.var-chip--ok .var-chip__qty {
    background: color-mix(in srgb, #10b981 14%, transparent);
    color: #059669;
}

.var-chip--low {
    border-color: color-mix(in srgb, #f59e0b 45%, var(--surface-border));
}

.var-chip--low .var-chip__qty {
    background: color-mix(in srgb, #f59e0b 16%, transparent);
    color: #d97706;
}

.var-chip--out {
    border-color: color-mix(in srgb, #ef4444 45%, var(--surface-border));
}

.var-chip--out .var-chip__qty {
    background: color-mix(in srgb, #ef4444 15%, transparent);
    color: #dc2626;
}

.var-chip--more {
    cursor: default;
    padding: 0.2rem 0.5rem;
    font-weight: 700;
    color: var(--text-color-secondary);
    background: var(--surface-ground);
}

.stock-status-cell {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 0.28rem;
}

.stock-tag-nowrap {
    white-space: nowrap !important;
}

.stock-sub-alert {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.72rem;
    font-weight: 700;
    color: #d97706;
    white-space: nowrap;
}

.row-quick-actions {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    justify-content: flex-end;
}

.row-chevron {
    width: 1.75rem;
    height: 1.75rem;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: var(--text-color-secondary);
    font-size: 0.78rem;
    transition:
        transform 0.15s ease,
        color 0.15s ease,
        background-color 0.15s ease;
}

.row-chevron.is-active {
    color: var(--primary-color);
    background: color-mix(
        in srgb,
        var(--primary-color) 12%,
        var(--surface-card)
    );
    transform: translateX(2px);
}

.medicine-pagination-summary {
    font-size: 0.82rem;
    color: var(--text-color-secondary);
}

.medicine-pagination-summary strong {
    color: var(--text-color);
}

/* Empty State Icon Ring */
.empty-state-icon-ring {
    width: 3.6rem;
    height: 3.6rem;
    border-radius: 50%;
    background: color-mix(
        in srgb,
        var(--primary-color) 10%,
        var(--surface-ground)
    );
    color: var(--primary-color);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 0.5rem;
}

/* Right Pane Details & Stock Controls */
.details-pane-container {
    padding: 1.35rem;
    display: flex;
    flex-direction: column;
    gap: 1.15rem;
}

.medicine-profile-header {
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--surface-border);
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.medicine-detail-identity {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.75rem;
}

.medicine-detail-identity__left {
    display: flex;
    align-items: center;
    gap: 0.8rem;
    min-width: 0;
}

.medicine-profile-avatar {
    width: 2.75rem !important;
    height: 2.75rem !important;
    background: color-mix(
        in srgb,
        var(--primary-color) 14%,
        var(--surface-card)
    ) !important;
    color: var(--primary-color) !important;
    font-size: 0.92rem !important;
    font-weight: 800 !important;
    flex-shrink: 0 !important;
}

.inspector-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-color-secondary);
}

.inspector-header-actions {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-shrink: 0;
}

.inspector-header-actions :deep(.p-button) {
    width: auto !important;
    white-space: nowrap !important;
}

.medicine-selected-title {
    margin: 0.1rem 0 0;
    font-size: 1.2rem;
    font-weight: 800;
    line-height: 1.2;
}

.medicine-selected-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
}

.badge-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    background: var(--surface-ground);
    color: var(--text-color-secondary);
    border: 1px solid var(--surface-border);
    padding: 0.25rem 0.6rem;
    border-radius: 6px;
    font-size: 0.74rem;
    font-weight: 700;
}

.badge-tag--teal {
    color: #0d9488;
    border-color: color-mix(in srgb, #0d9488 30%, var(--surface-border));
    background: color-mix(in srgb, #0d9488 8%, var(--surface-card));
}

.badge-tag--blue {
    color: #2563eb;
    border-color: color-mix(in srgb, #2563eb 30%, var(--surface-border));
    background: color-mix(in srgb, #2563eb 8%, var(--surface-card));
}

.inventory-card {
    background: var(--surface-ground);
    border: 1px solid var(--surface-border);
    border-radius: 10px;
    padding: 1.1rem;
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}

.inventory-card__top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
}

.medicine-detail-label {
    display: block;
    font-size: 0.68rem;
    font-weight: 700;
    color: var(--text-color-secondary);
    letter-spacing: 0.04em;
    margin-bottom: 0.15rem;
}

.current-stock-big {
    font-size: 1.45rem;
    font-weight: 800;
    color: var(--text-color);
}

.current-stock-big small {
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--text-color-secondary);
}

.quick-step-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    flex-wrap: wrap;
    padding: 0.55rem 0.75rem;
    background: var(--surface-card);
    border: 1px solid var(--surface-border);
    border-radius: 8px;
}

.quick-step-bar__label {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--text-color-secondary);
}

.quick-step-bar__label :deep(svg) {
    color: #f59e0b;
    flex-shrink: 0;
}

.quick-step-pills {
    display: flex;
    flex-wrap: wrap;
    gap: 0.3rem;
}

.step-pill {
    padding: 0.24rem 0.54rem;
    border-radius: 6px;
    font-family: inherit;
    font-size: 0.74rem;
    font-weight: 800;
    cursor: pointer;
    border: 1px solid var(--surface-border);
    background: var(--surface-ground);
    color: var(--text-color);
    transition: all 0.14s ease;
}

.step-pill--minus:hover {
    border-color: #ef4444;
    color: #dc2626;
    background: color-mix(in srgb, #ef4444 8%, var(--surface-card));
}

.step-pill--plus:hover {
    border-color: #10b981;
    color: #059669;
    background: color-mix(in srgb, #10b981 8%, var(--surface-card));
}

.qty-form {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
    margin-top: 0.25rem;
}

.qty-stepper-row {
    display: grid;
    grid-template-columns: 2.5rem 1fr 2.5rem;
    gap: 0.45rem;
    align-items: center;
}

.qty-step-icon-btn {
    width: 2.5rem;
    height: 2.5rem;
    padding: 0;
    line-height: 1;
    border-radius: 8px;
    border: 1px solid var(--surface-border);
    background: var(--surface-card);
    color: var(--text-color);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.14s ease;
}

.qty-step-icon-btn:hover:not(:disabled) {
    border-color: var(--primary-color);
    color: var(--primary-color);
}

.qty-step-icon-btn--plus:hover:not(:disabled) {
    border-color: #10b981;
    color: #059669;
}

.qty-step-icon-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

.qty-stepper-row :deep(.p-inputnumber-input) {
    text-align: center;
    font-weight: 800;
    font-size: 1rem;
    height: 2.5rem;
}

.qty-unsaved-actions {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 0.5rem;
    align-items: center;
}

.qty-sync-status {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.78rem;
    font-weight: 600;
}

.qty-sync-status--fresh {
    color: var(--text-color-secondary);
}

/* Variations Family Grid */
.variation-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.65rem;
}

.sub-title {
    font-size: 0.92rem;
    font-weight: 800;
    margin: 0;
    color: var(--text-color);
}

.family-total-sub {
    font-size: 0.75rem;
    color: var(--text-color-secondary);
}

.badge-count {
    font-size: 0.75rem;
    font-weight: 700;
    background: var(--surface-ground);
    padding: 0.2rem 0.55rem;
    border-radius: 999px;
    color: var(--text-color-secondary);
    border: 1px solid var(--surface-border);
}

.variations-grid {
    display: flex;
    flex-direction: column;
    gap: 0.55rem;
}

.variation-card {
    background: var(--surface-card);
    border: 1px solid var(--surface-border);
    border-radius: 8px;
    padding: 0.75rem 0.9rem;
    transition: all 0.15s ease;
    width: 100%;
    text-align: left;
    font-family: inherit;
}

.variation-card--interactive {
    cursor: pointer;
}

.variation-card--interactive:hover {
    border-color: var(--primary-color);
}

.variation-card.is-selected-card {
    border: 1.5px solid var(--primary-color);
    background: color-mix(
        in srgb,
        var(--primary-color) 6%,
        var(--surface-card)
    );
}

.variation-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
}

.variation-card-header--centered {
    justify-content: center;
    min-height: 1.8rem;
    gap: 0.45rem;
}

.variation-name {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.86rem;
    color: var(--text-color);
}

.variation-quantity-copy {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    font-size: 0.76rem;
    color: var(--text-color-secondary);
    margin-top: 0.2rem;
}

.selected-indicator {
    display: inline-flex;
    align-items: center;
    padding: 0.08rem 0.42rem;
    border-radius: 999px;
    background: color-mix(
        in srgb,
        var(--primary-color) 14%,
        var(--surface-card)
    );
    color: var(--primary-color);
    font-size: 0.68rem;
    font-weight: 800;
}

.variation-inline-stepper {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    flex-shrink: 0;
    background: var(--surface-ground);
    padding: 0.2rem;
    border-radius: 7px;
    border: 1px solid var(--surface-border);
}

.mini-step-qty {
    min-width: 1.6rem;
    text-align: center;
    font-size: 0.76rem;
    font-weight: 800;
    color: var(--text-color);
}

.mini-step-btn {
    width: 1.75rem;
    height: 1.75rem;
    padding: 0;
    line-height: 1;
    border-radius: 5px;
    border: 1px solid var(--surface-border);
    background: var(--surface-card);
    color: var(--text-color-secondary);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    cursor: pointer;
    transition: all 0.12s ease;
}

.mini-step-btn:hover:not(:disabled) {
    border-color: var(--primary-color);
    color: var(--text-color);
}

.mini-step-btn--plus:hover:not(:disabled) {
    border-color: #10b981;
    color: #059669;
}

.mini-step-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

.variation-card.dotted-card {
    border: 1.5px dashed var(--surface-border);
    background: transparent;
    color: var(--text-color-secondary);
}

.variation-card.dotted-card:hover {
    border-color: var(--primary-color);
    color: var(--primary-color);
    background: color-mix(in srgb, var(--primary-color) 4%, transparent);
}

.variation-card-add-icon {
    font-size: 0.85rem;
}

.variation-card-add-label {
    font-weight: 700;
    font-size: 0.82rem;
}

/* Selection Placeholder */
.selection-placeholder {
    padding: 3.5rem 2rem;
    text-align: center;
}

/* Categories & Sizes Config Views */
.content-split {
    display: grid;
    grid-template-columns: 1fr 1.6fr;
    gap: 1.25rem;
    align-items: start;
}

.config-form-head {
    display: flex;
    align-items: center;
    gap: 0.85rem;
}

.config-form-icon {
    width: 2.75rem;
    height: 2.75rem;
    border-radius: 10px;
    background: color-mix(in srgb, var(--primary-color) 12%, transparent);
    color: var(--primary-color);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.config-form-icon--blue {
    background: color-mix(in srgb, #2563eb 12%, transparent);
    color: #2563eb;
}

.section-heading-tight {
    margin: 0 0 0.2rem;
}

.quick-presets-box {
    margin-top: 1.25rem;
    padding-top: 1rem;
    border-top: 1px dashed var(--surface-border);
    display: flex;
    flex-direction: column;
    gap: 0.55rem;
}

.quick-presets-box__label {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.74rem;
    font-weight: 700;
    color: var(--text-color-secondary);
}

.quick-presets-box__label :deep(svg) {
    color: #f59e0b;
    flex-shrink: 0;
}

.quick-preset-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.38rem;
}

.preset-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.28rem;
    padding: 0.28rem 0.65rem;
    border-radius: 999px;
    border: 1px solid var(--surface-border);
    background: var(--surface-ground);
    color: var(--text-color-secondary);
    font-family: inherit;
    font-size: 0.74rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.14s ease;
}

.preset-chip:hover:not(:disabled) {
    border-color: var(--primary-color);
    color: var(--primary-color);
}

.preset-chip.is-active {
    background: color-mix(
        in srgb,
        var(--primary-color) 12%,
        var(--surface-card)
    );
    border-color: var(--primary-color);
    color: var(--primary-color);
}

.config-list-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 1rem;
    flex-wrap: wrap;
}

.config-search-wrap {
    position: relative;
    display: flex;
    align-items: center;
}

.config-search-wrap i {
    position: absolute;
    left: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    font-size: 0.8rem;
    color: var(--text-color-secondary);
    pointer-events: none;
    z-index: 1;
}

.config-search-wrap :deep(.p-inputtext) {
    padding-left: 2.15rem !important;
    width: 14rem;
}

.config-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(225px, 1fr));
    gap: 0.75rem;
}

.config-item-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.65rem;
    padding: 0.75rem 0.9rem;
    background: var(--surface-ground);
    border: 1px solid var(--surface-border);
    border-radius: 9px;
    transition: border-color 0.15s ease;
}

.config-item-card:hover {
    border-color: color-mix(
        in srgb,
        var(--primary-color) 35%,
        var(--surface-border)
    );
}

.config-item-left {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    min-width: 0;
}

.config-item-badge {
    width: 2.2rem;
    height: 2.2rem;
    border-radius: 8px;
    background: color-mix(
        in srgb,
        var(--primary-color) 12%,
        var(--surface-card)
    );
    color: var(--primary-color);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.config-item-badge--blue {
    background: color-mix(in srgb, #2563eb 12%, var(--surface-card));
    color: #2563eb;
}

.config-item-info {
    display: flex;
    flex-direction: column;
    gap: 0.12rem;
    min-width: 0;
}

.config-usage-pill {
    font-size: 0.7rem;
    font-weight: 600;
    color: var(--text-color-secondary);
}

.config-item-actions {
    display: flex;
    align-items: center;
    gap: 0.2rem;
    flex-shrink: 0;
}

.config-edit-row {
    display: grid;
    grid-template-columns: 1fr auto auto;
    gap: 0.35rem;
    align-items: center;
    width: 100%;
}

.empty-state--compact {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 2.5rem 1rem;
    text-align: center;
}

.empty-state--compact__icon {
    color: var(--text-color-secondary);
    opacity: 0.65;
}

/* Form utility & Dialog Styles (matching CourierParcels & MedicalCertificates) */
.btn-auto {
    width: auto !important;
    white-space: nowrap !important;
}

.simple-stack {
    display: flex;
    flex-direction: column;
    gap: 0.9rem;
}

.dialog-form {
    display: flex;
    flex-direction: column;
    gap: 1.15rem;
}

.dialog-body {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.field {
    display: flex;
    flex-direction: column;
    gap: 0.38rem;
}

.field-label-strong,
.form-label {
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--text-color);
}

.required {
    color: #e11d48;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.9rem;
}

.field-header-inline {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}

.inline-link-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0;
    border: none;
    background: transparent;
    color: var(--primary-color);
    font-family: inherit;
    font-size: 0.75rem;
    font-weight: 700;
    cursor: pointer;
    line-height: 1;
}

.inline-link-btn:hover {
    text-decoration: underline;
}

.inline-link-btn i {
    font-size: 0.68rem;
}

/* Selected Medicine Banner */
.selected-med-banner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.85rem;
    padding: 0.75rem 0.95rem;
    border-radius: 10px;
    background: color-mix(
        in srgb,
        var(--primary-color) 7%,
        var(--surface-card)
    );
    border: 1px solid
        color-mix(in srgb, var(--primary-color) 28%, var(--surface-border));
}

.selected-med-banner__left {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    min-width: 0;
}

.selected-med-banner__avatar {
    width: 2.35rem !important;
    height: 2.35rem !important;
    background: var(--primary-color) !important;
    color: var(--primary-contrast-color) !important;
    font-size: 0.8rem !important;
    font-weight: 700;
}

.selected-med-banner__info {
    display: flex;
    flex-direction: column;
    gap: 0.12rem;
    min-width: 0;
}

.selected-med-banner__title {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.9rem;
    color: var(--text-color);
    flex-wrap: wrap;
}

.med-catalog-badge {
    font-size: 0.68rem;
    font-weight: 700;
    padding: 0.1rem 0.45rem;
    border-radius: 999px;
    background: var(--surface-card);
    border: 1px solid var(--surface-border);
    color: var(--primary-color);
}

.selected-med-banner__meta {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.75rem;
    color: var(--text-color-secondary);
}

/* Variations Stock Quantities Section */
.variations-group {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
    padding: 0.9rem 1rem;
    border-radius: 10px;
    background: var(--surface-ground);
    border: 1px solid var(--surface-border);
}

.variations-group__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.variations-group__title {
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--text-color);
}

.qty-preset-strip {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    flex-wrap: wrap;
}

.qty-preset-strip__label {
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--text-color-secondary);
    margin-right: 0.15rem;
}

.qty-preset-btn {
    padding: 0.2rem 0.55rem;
    border-radius: 6px;
    border: 1px solid var(--surface-border);
    background: var(--surface-card);
    color: var(--text-color-secondary);
    font-family: inherit;
    font-size: 0.73rem;
    font-weight: 700;
    line-height: 1.2;
    cursor: pointer;
    transition: all 0.14s ease;
}

.qty-preset-btn:hover,
.qty-preset-btn.is-active {
    border-color: var(--primary-color);
    color: var(--primary-color);
    background: color-mix(
        in srgb,
        var(--primary-color) 10%,
        var(--surface-card)
    );
}

.variations-group__list {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
    max-height: 15rem;
    overflow-y: auto;
}

.variation-stock-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.5rem 0.75rem;
    border-radius: 8px;
    background: var(--surface-card);
    border: 1px solid var(--surface-border);
}

.variation-stock-row__left {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    flex-wrap: wrap;
    min-width: 0;
}

.variation-stock-row__combo {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--text-color);
}

.variation-potency {
    color: var(--primary-color);
}

.variation-sep {
    color: var(--text-color-secondary);
}

.variation-existing-badge {
    font-size: 0.68rem;
    font-weight: 700;
    padding: 0.12rem 0.45rem;
    border-radius: 999px;
    background: var(--surface-ground);
    border: 1px solid var(--surface-border);
    color: var(--text-color-secondary);
}

.variation-stock-row__right {
    width: 8.25rem;
    flex-shrink: 0;
}

.variation-qty-input :deep(.p-inputnumber-input) {
    text-align: center;
    font-weight: 700;
    padding: 0.35rem 0.4rem !important;
}

.variation-qty-input :deep(.p-inputnumber-button) {
    width: 2.1rem !important;
    padding: 0 !important;
    flex-shrink: 0;
}

.dialog-footer {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.65rem;
    padding-top: 1rem;
    border-top: 1px solid var(--surface-border);
}

@media (max-width: 1100px) {
    .inventory-stats-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .ledger-filter-row {
        grid-template-columns: 1fr 1fr;
    }

    .medicine-search-input-wrap {
        grid-column: span 2;
    }
}

@media (max-width: 960px) {
    .medicines-split-view,
    .content-split {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 640px) {
    .inventory-stats-grid,
    .ledger-filter-row,
    .form-row {
        grid-template-columns: 1fr;
    }

    .medicine-search-input-wrap {
        grid-column: span 1;
    }
}
</style>
