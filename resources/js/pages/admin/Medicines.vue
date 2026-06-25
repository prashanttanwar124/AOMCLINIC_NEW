<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import SelectButton from 'primevue/selectbutton';
import Tag from 'primevue/tag';
import { computed, ref, watch } from 'vue';
import { dashboard } from '@/routes';
import { medicines as medicinesIndexRoute } from '@/routes/admin';
import medicinesRoutes from '@/routes/admin/medicines';
import categoriesRoute from '@/routes/admin/categories';
import sizesRoute from '@/routes/admin/sizes';

// Types
type Category = {
    id: number;
    name: string;
};

type Size = {
    id: number;
    name: string;
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
    filters: {
        search: string | null;
    };
}>();

// Active main tab
const activeTab = ref<'ledger' | 'categories' | 'sizes'>('ledger');

// Search query
const searchQuery = ref(props.filters.search ?? '');

// Inline Quantities map: maps medicine ID to its edited quantity
const editedQuantities = ref<Record<number, number>>({});
const tempQuantity = ref(0);

// Selected medicine name & variation ID for detailed split view
const selectedMedicineName = ref<string | null>(null);
const selectedVariationId = ref<number | null>(null);

// Status filtering happens server-side, so the page data is already scoped.
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

    return grouped.variations.find((v) => v.id === selectedVariationId.value) ?? grouped.variations[0];
});

const relatedVariations = computed<Medicine[]>(() => {
    const grouped = selectedGroupedMedicine.value;
    const current = selectedMedicine.value;
    if (!grouped || !current) {
        return [];
    }

    return (grouped.variations ?? []).filter((v) => v.id !== current.id);
});

const inventoryTabOptions = [
    { label: 'Medicines Ledger', value: 'ledger', icon: 'pi pi-box' },
    { label: 'Category Options', value: 'categories', icon: 'pi pi-sliders-h' },
    { label: 'Size Options', value: 'sizes', icon: 'pi pi-sort-amount-up' },
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
        selectedVariationId.value = props.medicines.data[0].variations?.[0]?.id ?? null;
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
                const stillExists = newData.some((m) => m.name === selectedMedicineName.value);
                if (!stillExists) {
                    selectedMedicineName.value = newData[0].name;
                    selectedVariationId.value = newData[0].variations?.[0]?.id ?? null;
                } else {
                    const grouped = newData.find((m) => m.name === selectedMedicineName.value);
                    const varExists = grouped?.variations?.some((v) => v.id === selectedVariationId.value);
                    if (!varExists) {
                        selectedVariationId.value = grouped?.variations?.[0]?.id ?? null;
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

function applyQuery(): void {
    router.get(
        medicinesIndexRoute().url,
        {
            search: searchQuery.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
}

function handleSearch(): void {
    applyQuery();
}

function handleClearSearch(): void {
    searchQuery.value = '';
    applyQuery();
}

function clearFilters(): void {
    searchQuery.value = '';
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

function selectGrouped(grouped: any): void {
    selectedMedicineName.value = grouped.name;
    selectedVariationId.value = grouped.variations?.[0]?.id ?? null;
}

function selectVariation(variation: Medicine): void {
    selectedVariationId.value = variation.id;
}

// Update single medicine quantity
const updatingQuantityId = ref<number | null>(null);
function saveQuantity(medicineId: number): void {
    updatingQuantityId.value = medicineId;
    router.patch(
        medicinesRoutes.quantity.update(medicineId).url,
        {
            quantity: tempQuantity.value,
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

// Delete medicine variation
function deleteMedicine(medicine: Medicine): void {
    if (
        confirm(
            `Are you sure you want to delete variation: ${medicine.name} (${medicine.category?.name} - ${medicine.size?.name})?`,
        )
    ) {
        router.delete(medicinesRoutes.destroy(medicine.id).url, {
            preserveScroll: true,
            preserveState: true,
        });
    }
}

// New Medicine Modal state & form
const showNewMedicineModal = ref(false);
const newMedicineName = ref('');
const selectedCategoryIds = ref<number[]>([]);
const selectedSizeIds = ref<number[]>([]);
const permutationQuantities = ref<Record<string, number>>({});
const isSubmittingMedicine = ref(false);

const permutations = computed(() => {
    const name = newMedicineName.value.trim();

    if (
        !name ||
        selectedCategoryIds.value.length === 0 ||
        selectedSizeIds.value.length === 0
    ) {
        return [];
    }

    const result = [];

    for (const catId of selectedCategoryIds.value) {
        const cat = props.categories.find((c) => c.id === catId);

        for (const sizeId of selectedSizeIds.value) {
            const sz = props.sizes.find((s) => s.id === sizeId);
            const key = `${catId}-${sizeId}`;
            result.push({
                category_id: catId,
                category_name: cat ? cat.name : '',
                size_id: sizeId,
                size_name: sz ? sz.name : '',
                key,
            });
        }
    }

    return result;
});

// Watch permutations to sync permutationQuantities
watch(permutations, (newPerms) => {
    newPerms.forEach((p) => {
        if (permutationQuantities.value[p.key] === undefined) {
            permutationQuantities.value[p.key] = 0;
        }
    });
});

// Watch modal visibility to reset inputs when closed
watch(showNewMedicineModal, (visible) => {
    if (!visible) {
        resetNewMedicineForm();
    }
});

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
    selectedCategoryIds.value = [];
    selectedSizeIds.value = [];
    permutationQuantities.value = {};
}

// Helper to open variations modal with prefilled name
function openAddVariation(): void {
    if (selectedMedicine.value) {
        newMedicineName.value = selectedMedicine.value.name;
    }

    showNewMedicineModal.value = true;
}

// Category Management Form
const newCategoryName = ref('');
const isSubmittingCategory = ref(false);
function submitNewCategory(): void {
    if (!newCategoryName.value.trim()) {
        return;
    }

    isSubmittingCategory.value = true;
    router.post(
        categoriesRoute.store().url,
        {
            name: newCategoryName.value.trim(),
        },
        {
            onSuccess: () => {
                newCategoryName.value = '';
            },
            onFinish: () => {
                isSubmittingCategory.value = false;
            },
        },
    );
}

function deleteCategory(category: Category): void {
    if (
        confirm(
            `Are you sure you want to delete category option "${category.name}"? This might fail if it is assigned to existing medicines.`,
        )
    ) {
        router.delete(categoriesRoute.destroy(category.id).url, {
            preserveScroll: true,
            preserveState: true,
        });
    }
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
const isSubmittingSize = ref(false);
function submitNewSize(): void {
    if (!newSizeName.value.trim()) {
        return;
    }

    isSubmittingSize.value = true;
    router.post(
        sizesRoute.store().url,
        {
            name: newSizeName.value.trim(),
        },
        {
            onSuccess: () => {
                newSizeName.value = '';
            },
            onFinish: () => {
                isSubmittingSize.value = false;
            },
        },
    );
}

function deleteSize(size: Size): void {
    if (
        confirm(
            `Are you sure you want to delete size option "${size.name}"? This might fail if it is assigned to existing medicines.`,
        )
    ) {
        router.delete(sizesRoute.destroy(size.id).url, {
            preserveScroll: true,
            preserveState: true,
        });
    }
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

function getStockStatusColor(quantity: number): string {
    if (quantity === 0) {
        return '#ef4444';
    } // Red

    if (quantity < 10) {
        return '#f59e0b';
    } // Orange

    return '#10b981'; // Green
}

function getStockStatusSeverity(quantity: number): string {
    if (quantity === 0) {
        return 'danger';
    }

    if (quantity < 10) {
        return 'warn';
    }

    return 'success';
}
</script>

<template>
    <Head title="Medicines Inventory" />

    <div class="page-grid">
        <!-- Hero header -->
        <section class="page-card medicines-hero">
            <div class="medicines-hero__copy">
                <p class="stat-label">Inventory control</p>
                <h2 class="medicines-hero__title">
                    Medicines &amp; stock ledger
                </h2>
                <p class="panel-subtitle medicines-hero__subtitle">
                    Manage your medicine catalog, categories and container
                    sizes, and keep stock counts accurate across every
                    variation.
                </p>
            </div>
            <div class="medicines-hero__actions">
                <Button
                    label="New Medicine"
                    icon="pi pi-plus"
                    class="p-button-primary flat-btn"
                    @click="showNewMedicineModal = true"
                />
            </div>
        </section>



        <!-- Navigation Tabs Header -->
        <div class="tabs-header-container">
            <SelectButton
                v-model="activeTab"
                :options="inventoryTabOptions"
                option-label="label"
                option-value="value"
                class="inventory-tab-switcher"
                aria-label="Inventory sections"
            >
                <template #option="{ option }">
                    <span class="inventory-switch-option">
                        <i :class="option.icon"></i>
                        <span>{{ option.label }}</span>
                    </span>
                </template>
            </SelectButton>
        </div>

        <!-- Ledger Tab View (Split View) -->
        <div v-if="activeTab === 'ledger'" class="tab-pane-container">
            <div class="medicines-split-view">
                <!-- Left Pane: Medicines registry -->
                <article class="page-card admin-table-card medicines-list-pane">
                    <header class="admin-toolbar medicine-list-toolbar">
                        <div class="admin-search medicine-search-row">
                            <InputText
                                v-model="searchQuery"
                                placeholder="Search by name..."
                                class="admin-search__input"
                                @keyup.enter="handleSearch"
                            />
                            <Button
                                v-if="searchQuery"
                                icon="pi pi-times"
                                severity="secondary"
                                outlined
                                size="small"
                                class="flat-btn search-action-btn"
                                @click="handleClearSearch"
                            />
                        </div>
                    </header>

                    <DataTable
                        v-if="filteredMedicinesList.length"
                        :value="filteredMedicinesList"
                        responsive-layout="scroll"
                        :row-class="
                            (data) => ({
                                'is-selected': selectedGroupedMedicine?.name === data.name,
                            })
                        "
                        @row-click="(e) => selectGrouped(e.data)"
                    >
                        <Column header="Medicine" style="min-width: 12rem">
                            <template #body="{ data }">
                                <div class="medicine-info-cell">
                                    <div>
                                        <div
                                            class="medicine-title medicine-title--strong"
                                        >
                                            {{ data.name }}
                                        </div>
                                        <div class="medicine-meta">
                                            {{ data.variations?.length ?? 0 }} variation(s)
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </Column>
                        <Column
                            header="Stock Status"
                            style="width: 10rem; min-width: 10rem"
                        >
                            <template #body="{ data }">
                                <Tag
                                    :value="getStockStatusLabel(data.total_quantity)"
                                    :severity="
                                        getStockStatusSeverity(data.total_quantity)
                                    "
                                />
                            </template>
                        </Column>
                        <Column
                            header=""
                            class="text-right"
                            header-class="text-right"
                            style="width: 3rem; min-width: 3rem"
                        >
                            <template #body>
                                <i
                                    class="pi pi-chevron-right medicine-chevron"
                                ></i>
                            </template>
                        </Column>
                    </DataTable>

                    <div v-else class="empty-state medicines-empty-state">
                        <i class="pi pi-box medicines-empty-state__icon"></i>
                        <h3>No medicines found</h3>
                        <p>
                            <template
                                v-if="searchQuery"
                            >
                                No variations match the current filter or
                                search.
                            </template>
                            <template v-else>
                                Get started by adding your first medicine to the
                                catalog.
                            </template>
                        </p>
                        <Button
                            v-if="searchQuery"
                            label="Clear filters"
                            icon="pi pi-filter-slash"
                            severity="secondary"
                            outlined
                            size="small"
                            class="flat-btn medicines-empty-state__action"
                            @click="clearFilters"
                        />
                        <Button
                            v-else
                            label="New Medicine"
                            icon="pi pi-plus"
                            size="small"
                            class="p-button-primary flat-btn medicines-empty-state__action"
                            @click="showNewMedicineModal = true"
                        />
                    </div>

                    <!-- Pagination navigation -->
                    <div
                        v-if="
                            props.medicines.links &&
                            props.medicines.links.length > 3
                        "
                        class="admin-pagination-container"
                    >
                        <div class="admin-pagination">
                            <button
                                v-for="link in props.medicines.links"
                                :key="link.label"
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
                    <div v-if="selectedMedicine" class="details-pane-container">
                        <header class="medicine-profile-header">
                            <div class="medicine-detail-identity w-full flex justify-content-between align-items-start">
                                <div>
                                    <span class="stat-label"
                                        >Selected Variation</span
                                    >
                                    <h3
                                        class="panel-title medicine-selected-title"
                                    >
                                        {{ selectedMedicine.name }}
                                    </h3>
                                    <div class="medicine-selected-badges">
                                        <span class="badge-tag"
                                            >ID #{{ selectedMedicine.id }}</span
                                        >
                                        <span class="badge-tag"
                                            >Strength:
                                            {{
                                                selectedMedicine.category
                                                    ?.name ?? 'N/A'
                                            }}</span
                                        >
                                        <span class="badge-tag"
                                            >Volume:
                                            {{
                                                selectedMedicine.size?.name ??
                                                'N/A'
                                            }}</span
                                        >
                                    </div>
                                </div>
                                <Button
                                    icon="pi pi-trash"
                                    severity="danger"
                                    outlined
                                    size="small"
                                    class="flat-btn"
                                    @click="deleteMedicine(selectedMedicine)"
                                />
                            </div>
                        </header>

                        <!-- Stock Control Card -->
                        <div class="inventory-card">
                            <div class="flex justify-content-between align-items-center mb-3">
                                <div>
                                    <span class="detail-label medicine-detail-label text-xs font-semibold">CURRENT STOCK</span>
                                    <span class="font-bold text-xl">{{ selectedMedicine.quantity }} units</span>
                                </div>
                                <Tag
                                    :value="getStockStatusLabel(selectedMedicine.quantity)"
                                    :severity="getStockStatusSeverity(selectedMedicine.quantity)"
                                />
                            </div>

                            <div class="field mb-1">
                                <label for="medicine-quantity" class="field-label-strong text-sm">Adjust Stock Level</label>
                                <div class="qty-form">
                                    <InputNumber
                                        v-model="tempQuantity"
                                        input-id="medicine-quantity"
                                        :min="0"
                                        show-buttons
                                        fluid
                                        class="medicine-quantity-input"
                                    />
                                    <Button
                                        v-if="tempQuantity !== selectedMedicine.quantity"
                                        label="Update Stock"
                                        icon="pi pi-check"
                                        severity="success"
                                        class="flat-btn inventory-card__action"
                                        :loading="updatingQuantityId === selectedMedicine.id"
                                        @click="saveQuantity(selectedMedicine.id)"
                                    />
                                    <span
                                        v-else
                                        class="qty-sync-status qty-sync-status--fresh"
                                    >
                                        <i class="pi pi-check-circle qty-sync-status__icon"></i>
                                        Stock level is up to date
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Product Family variations -->
                        <div class="medicine-variations-section">
                            <div
                                class="admin-toolbar admin-toolbar--compact variation-toolbar"
                            >
                                <h4 class="sub-title">Product Family</h4>
                                <span class="badge-count"
                                    >{{
                                        selectedGroupedMedicine?.variations?.length ?? 0
                                    }}
                                    variations</span
                                >
                            </div>

                            <div
                                class="variations-grid variations-grid--padded"
                            >
                                <div
                                    v-for="variation in selectedGroupedMedicine?.variations"
                                    :key="variation.id"
                                    class="variation-card variation-card--interactive"
                                    :class="{ 'is-selected-card': selectedMedicine?.id === variation.id }"
                                    @click="selectVariation(variation)"
                                >
                                    <div class="variation-card-header">
                                        <div class="flex-grow">
                                            <div
                                                class="font-bold variation-name"
                                            >
                                                {{ variation.category?.name ?? 'N/A' }} · {{ variation.size?.name ?? 'N/A' }}
                                            </div>
                                            <div
                                                class="variation-quantity-copy"
                                            >
                                                Qty: {{ variation.quantity }}
                                                <span v-if="selectedMedicine?.id === variation.id" class="text-primary font-bold ml-1">
                                                    · Selected
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Add variation dotted card -->
                                <div
                                    class="variation-card dotted-card variation-card--interactive"
                                    @click="openAddVariation"
                                >
                                    <div
                                        class="variation-card-header variation-card-header--centered"
                                    >
                                        <i
                                            class="pi pi-plus variation-card-add-icon"
                                        ></i>
                                        <span class="variation-card-add-label"
                                            >Add variation</span
                                        >
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        v-else
                        class="empty-state selection-placeholder medicine-selection-empty-state"
                    >
                        <i class="pi pi-box"></i>
                        <h3>No medicine selected</h3>
                        <p>
                            Click a medicine row in the ledger registry on the
                            left to see details and inventory controls.
                        </p>
                    </div>
                </article>
            </div>
        </div>

        <!-- Category Options Tab View -->
        <div v-if="activeTab === 'categories'" class="tab-pane-container">
            <div class="content-split">
                <!-- Add Category Form -->
                <article class="page-card">
                    <h3 class="panel-title section-heading-tight">
                        Add Category Option
                    </h3>
                    <p class="panel-subtitle section-copy-spaced">
                        Create a new category value that can be assigned to
                        medicines.
                    </p>
                    <form
                        @submit.prevent="submitNewCategory"
                        class="simple-stack"
                    >
                        <div class="field">
                            <label for="categoryName" class="field-label-strong"
                                >Category Name</label
                            >
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
                </article>

                <!-- Category List -->
                <article class="page-card">
                    <h3 class="panel-title section-heading-tight">
                        Configured Categories
                    </h3>
                    <p class="panel-subtitle section-copy-spaced">
                        All available category values.
                    </p>

                    <div v-if="props.categories.length" class="config-grid">
                        <div
                            v-for="category in props.categories"
                            :key="category.id"
                            class="config-item-card"
                        >
                            <template v-if="editingCategoryId === category.id">
                                <div class="flex gap-2 align-items-center w-full">
                                    <InputText
                                        v-model="editingCategoryName"
                                        size="small"
                                        class="flex-grow w-full"
                                        @keyup.enter="saveCategoryEdit(category.id)"
                                    />
                                    <Button
                                        icon="pi pi-check"
                                        severity="success"
                                        size="small"
                                        class="flat-btn shrink-0"
                                        @click="saveCategoryEdit(category.id)"
                                    />
                                    <Button
                                        icon="pi pi-times"
                                        severity="secondary"
                                        size="small"
                                        class="flat-btn shrink-0"
                                        @click="editingCategoryId = null"
                                    />
                                </div>
                            </template>
                            <template v-else>
                                <span class="config-item-title">{{ category.name }}</span>
                                <div class="flex gap-2">
                                    <Button
                                        icon="pi pi-pencil"
                                        severity="secondary"
                                        outlined
                                        size="small"
                                        class="flat-btn shrink-0 config-edit-btn"
                                        @click="startEditCategory(category)"
                                    />
                                    <Button
                                        icon="pi pi-trash"
                                        severity="danger"
                                        outlined
                                        size="small"
                                        class="flat-btn shrink-0 config-delete-btn"
                                        @click="deleteCategory(category)"
                                    />
                                </div>
                            </template>
                        </div>
                    </div>
                    <div v-else class="empty-state empty-state--compact">
                        <i
                            class="pi pi-sliders-h empty-state--compact__icon"
                        ></i>
                        <p class="text-secondary">No categories defined yet.</p>
                    </div>
                </article>
            </div>
        </div>

        <!-- Size Options Tab View -->
        <div v-if="activeTab === 'sizes'" class="tab-pane-container">
            <div class="content-split">
                <!-- Add Size Form -->
                <article class="page-card">
                    <h3 class="panel-title section-heading-tight">
                        Add Size Option
                    </h3>
                    <p class="panel-subtitle section-copy-spaced">
                        Create a new container size option.
                    </p>
                    <form @submit.prevent="submitNewSize" class="simple-stack">
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
                </article>

                <!-- Size List -->
                <article class="page-card">
                    <h3 class="panel-title section-heading-tight">
                        Configured Sizes
                    </h3>
                    <p class="panel-subtitle section-copy-spaced">
                        All available container sizes.
                    </p>

                    <div v-if="props.sizes.length" class="config-grid">
                        <div
                            v-for="size in props.sizes"
                            :key="size.id"
                            class="config-item-card"
                        >
                            <template v-if="editingSizeId === size.id">
                                <div class="flex gap-2 align-items-center w-full">
                                    <InputText
                                        v-model="editingSizeName"
                                        size="small"
                                        class="flex-grow w-full"
                                        @keyup.enter="saveSizeEdit(size.id)"
                                    />
                                    <Button
                                        icon="pi pi-check"
                                        severity="success"
                                        size="small"
                                        class="flat-btn shrink-0"
                                        @click="saveSizeEdit(size.id)"
                                    />
                                    <Button
                                        icon="pi pi-times"
                                        severity="secondary"
                                        size="small"
                                        class="flat-btn shrink-0"
                                        @click="editingSizeId = null"
                                    />
                                </div>
                            </template>
                            <template v-else>
                                <span class="config-item-title">{{ size.name }}</span>
                                <div class="flex gap-2">
                                    <Button
                                        icon="pi pi-pencil"
                                        severity="secondary"
                                        outlined
                                        size="small"
                                        class="flat-btn shrink-0 config-edit-btn"
                                        @click="startEditSize(size)"
                                    />
                                    <Button
                                        icon="pi pi-trash"
                                        severity="danger"
                                        outlined
                                        size="small"
                                        class="flat-btn shrink-0 config-delete-btn"
                                        @click="deleteSize(size)"
                                    />
                                </div>
                            </template>
                        </div>
                    </div>
                    <div v-else class="empty-state empty-state--compact">
                        <i
                            class="pi pi-sort-amount-up empty-state--compact__icon"
                        ></i>
                        <p class="text-secondary">No sizes defined yet.</p>
                    </div>
                </article>
            </div>
        </div>
    </div>

    <!-- Create Medicine Dialog -->
    <Dialog
        v-model:visible="showNewMedicineModal"
        modal
        dismissable-mask
        header="Create Medicine Variation(s)"
        class="medicine-dialog"
        :style="{ width: '48rem', maxWidth: '90vw' }"
    >
        <form @submit.prevent="submitNewMedicine" class="dialog-form-container">
            <div class="simple-stack">
                <!-- Medicine Name -->
                <div class="field">
                    <label for="medName" class="dialog-label"
                        >Medicine Name</label
                    >
                    <InputText
                        id="medName"
                        v-model="newMedicineName"
                        placeholder="e.g., Nux Vomica, Arnica Montana"
                        required
                    />
                </div>

                <div class="grid-2-col medicine-dialog-grid">
                    <!-- Category Select -->
                    <div class="selection-box">
                        <span class="dialog-label">Select Categories</span>
                        <div class="checkbox-list">
                            <div
                                v-for="category in props.categories"
                                :key="category.id"
                                class="checkbox-tile"
                                :class="{
                                    'is-selected': selectedCategoryIds.includes(
                                        category.id,
                                    ),
                                }"
                            >
                                <Checkbox
                                    :inputId="'cat-' + category.id"
                                    name="categories"
                                    :value="category.id"
                                    v-model="selectedCategoryIds"
                                />
                                <label
                                    :for="'cat-' + category.id"
                                    class="tile-label cursor-pointer"
                                    >{{ category.name }}</label
                                >
                            </div>
                            <div
                                v-if="!props.categories.length"
                                class="selection-empty-copy"
                            >
                                No category options configured. Please add
                                categories in the Category Options tab.
                            </div>
                        </div>
                    </div>

                    <!-- Size Select -->
                    <div class="selection-box">
                        <span class="dialog-label">Select Sizes</span>
                        <div class="checkbox-list">
                            <div
                                v-for="size in props.sizes"
                                :key="size.id"
                                class="checkbox-tile"
                                :class="{
                                    'is-selected': selectedSizeIds.includes(
                                        size.id,
                                    ),
                                }"
                            >
                                <Checkbox
                                    :inputId="'size-' + size.id"
                                    name="sizes"
                                    :value="size.id"
                                    v-model="selectedSizeIds"
                                />
                                <label
                                    :for="'size-' + size.id"
                                    class="tile-label cursor-pointer"
                                    >{{ size.name }}</label
                                >
                            </div>
                            <div
                                v-if="!props.sizes.length"
                                class="selection-empty-copy"
                            >
                                No size options configured. Please add sizes in
                                the Size Options tab.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Permutations Quantities Generator -->
                <div
                    v-if="permutations.length > 0"
                    class="permutations-section permutations-section--spaced"
                >
                    <span class="dialog-label permutations-section__label"
                        >Set Quantities for Selected Combinations</span
                    >
                    <div class="permutations-container">
                        <div
                            v-for="p in permutations"
                            :key="p.key"
                            class="permutation-row"
                        >
                            <div class="permutation-info">
                                <span class="perm-potency">{{
                                    p.category_name
                                }}</span>
                                <span class="perm-divider">·</span>
                                <span class="perm-size">{{ p.size_name }}</span>
                            </div>
                            <div class="permutation-qty">
                                <label class="qty-field-label">Qty:</label>
                                <InputNumber
                                    v-model="permutationQuantities[p.key]"
                                    :min="0"
                                    input-class="perm-qty-input"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Actions -->
            <div class="dialog-footer-actions">
                <Button
                    type="button"
                    label="Cancel"
                    severity="secondary"
                    outlined
                    class="flat-btn w-auto"
                    @click="showNewMedicineModal = false"
                />
                <Button
                    type="submit"
                    label="Save Variations"
                    icon="pi pi-check"
                    class="p-button-primary flat-btn w-auto"
                    :disabled="permutations.length === 0"
                    :loading="isSubmittingMedicine"
                />
            </div>
        </form>
    </Dialog>
</template>

<style scoped>
/* Hero header */
.medicines-hero {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1.25rem;
}

.medicines-hero__title {
    margin: 0.35rem 0 0;
    font-size: clamp(1.6rem, 2.4vw, 2.1rem);
    line-height: 1.1;
    font-weight: 700;
    color: var(--surface-900);
}

.medicines-hero__subtitle {
    margin-top: 0.6rem;
    max-width: 44rem;
}

.medicines-hero__actions {
    flex-shrink: 0;
}

/* Inventory health overview cards */
.inventory-stats-grid {
    display: grid;
    gap: 1rem;
    grid-template-columns: repeat(4, minmax(0, 1fr));
}

.inventory-stat-card {
    display: flex;
    align-items: center;
    gap: 0.9rem;
    padding: 1.1rem 1.25rem;
    background: var(--surface-card);
    border: 1px solid var(--surface-border);
    box-shadow: var(--card-shadow);
    cursor: pointer;
    text-align: left;
    font: inherit;
    transition:
        border-color 0.15s ease,
        box-shadow 0.15s ease,
        transform 0.1s ease;
}

.inventory-stat-card:hover {
    border-color: var(--p-primary-400);
    transform: translateY(-1px);
}

.inventory-stat-card.is-active {
    border-color: var(--p-primary-500);
    box-shadow: 0 0 0 1px var(--p-primary-500);
}

.inventory-stat-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.75rem;
    height: 2.75rem;
    flex-shrink: 0;
    font-size: 1.2rem;
}

.inventory-stat-icon.icon-all {
    background: color-mix(in srgb, var(--p-primary-500) 12%, transparent);
    color: var(--p-primary-600);
}

.inventory-stat-icon.icon-available {
    background: color-mix(in srgb, #10b981 14%, transparent);
    color: #059669;
}

.inventory-stat-icon.icon-low {
    background: color-mix(in srgb, #f59e0b 16%, transparent);
    color: #d97706;
}

.inventory-stat-icon.icon-out {
    background: color-mix(in srgb, #ef4444 14%, transparent);
    color: #dc2626;
}

.inventory-stat-meta {
    display: flex;
    flex-direction: column;
    line-height: 1.2;
}

.inventory-stat-value {
    font-size: 1.6rem;
    font-weight: 700;
    color: var(--text-color);
}

.inventory-stat-label {
    font-size: 0.82rem;
    font-weight: 600;
    color: var(--text-color-secondary);
}

/* Tabs Header & Triggers */
.tabs-header-container {
    display: flex;
    justify-content: flex-start;
}

/* Split View Layout */
.medicines-split-view {
    display: grid;
    grid-template-columns: 1.2fr 1fr;
    gap: 1.5rem;
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
        top: 1.5rem;
    }
}

.inventory-switch-option,
.inventory-filter-option {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.inventory-filter-option {
    justify-content: space-between;
    min-width: 7rem;
}

.inventory-filter-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 1.5rem;
    padding: 0.15rem 0.4rem;
    border-radius: 999px;
    background: color-mix(in srgb, currentColor 12%, transparent);
    font-size: 0.72rem;
    font-weight: 700;
}

.medicine-search-row,
.medicine-filter-row {
    width: 100%;
}

.medicine-list-toolbar {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 0.75rem;
}

.medicine-filter-row :deep(.p-selectbutton),
.tabs-header-container :deep(.p-selectbutton) {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
}

.medicine-filter-row :deep(.p-togglebutton) {
    border-radius: 6px !important;
}

.tabs-header-container :deep(.p-togglebutton) {
    border-radius: 6px !important;
    min-width: 12rem;
}

/* Medicine Info & Badge */
.medicine-info-cell {
    display: flex;
    align-items: center;
    gap: 0.85rem;
}

.medicine-avatar {
    background: var(--p-primary-500) !important;
    color: white !important;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.25rem;
    height: 2.25rem;
    border-radius: 50% !important;
    font-size: 0.9rem;
    font-weight: 700;
    flex-shrink: 0;
}

.medicine-title {
    font-size: 0.95rem;
    color: var(--text-color);
}

.medicine-title--strong,
.config-item-title {
    font-weight: 700;
}

.medicine-chevron {
    font-size: 0.875rem;
    color: var(--text-color-secondary);
}

.medicines-empty-state {
    border: none;
    padding-block: 2rem;
}

.medicines-empty-state__icon,
.empty-state--compact__icon {
    margin-bottom: 0.75rem;
    font-size: 2.25rem;
    color: var(--text-secondary-color);
}

.medicines-empty-state__action {
    margin-top: 0.75rem;
}

.medicine-meta {
    font-size: 0.82rem;
    color: var(--text-color-secondary);
    margin-top: 0.15rem;
    font-weight: 500;
}

.variation-quantity-copy {
    font-size: 0.75rem;
    color: var(--text-color-secondary);
}

/* Medicine Detail Pane */
.medicine-profile-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--surface-border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}

.medicine-selected-title {
    margin-top: 0.25rem;
    font-size: 1.25rem;
    font-weight: 700;
}

.medicine-selected-badges {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 0.25rem;
    flex-wrap: wrap;
}

.medicine-detail-identity {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.medicine-profile-avatar {
    background: var(--p-primary-600) !important;
    color: white !important;
    width: 3rem;
    height: 3rem;
    border-radius: 50% !important;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
    font-weight: 700;
    flex-shrink: 0;
}

.badge-tag {
    display: inline-block;
    padding: 0.15rem 0.5rem;
    font-size: 0.75rem;
    font-weight: 600;
    background: var(--surface-100);
    border: 1px solid var(--surface-border);
    color: var(--text-color-secondary);
}

.medicine-profile-detail {
    padding: 1.25rem;
    background: var(--surface-50);
    margin: 1.25rem;
    border: 1px solid var(--surface-border);
    display: grid;
    gap: 0.75rem 1rem;
    grid-template-columns: 1fr 1fr;
    font-size: 0.88rem;
}

.detail-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    color: var(--text-color);
}

.detail-item i {
    font-size: 1.05rem;
}

.medicine-detail-label {
    display: block;
    font-size: 0.75rem;
    color: var(--text-color-secondary);
}

.variation-toolbar {
    border: none;
    padding: 0.75rem 1rem;
}

.variations-grid--padded {
    padding: 0 1rem 1rem;
}

.variation-card--interactive {
    cursor: pointer;
}

.variation-card-header--centered {
    justify-content: center;
    padding-block: 0.5rem;
}

.variation-card-add-icon {
    margin-right: 0.5rem;
    color: var(--primary-color);
}

.variation-card-add-label {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--primary-color);
}

.inventory-card {
    padding: 1.25rem;
    background: var(--surface-50);
    margin: 1.25rem;
    border: 1px solid var(--surface-border);
}

.medicine-quantity-input {
    width: 100%;
}

.qty-form {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.qty-sync-status {
    color: var(--text-color-secondary);
}

.inventory-card__copy,
.danger-zone-card__copy {
    margin-bottom: 0.75rem;
    font-size: 0.875rem;
    color: var(--text-color-secondary);
}

.inventory-card__action {
    width: 100%;
    margin-top: 0.5rem;
}

.qty-sync-status--fresh {
    display: block;
    margin-top: 0.75rem;
    font-size: 0.75rem;
}

.qty-sync-status__icon {
    margin-right: 0.25rem;
    color: var(--p-green-500);
}

.danger-zone-card__inner {
    padding: 1rem;
    border-top: 1px solid var(--surface-border);
}

.danger-zone-card__title {
    margin-bottom: 0.25rem;
    color: var(--p-red-500);
}

.danger-zone-card__action {
    width: 100%;
}

.medicine-selection-empty-state {
    border: none;
    padding-block: 2rem;
}

.section-heading-tight {
    margin-bottom: 0.25rem;
}

.section-copy-spaced {
    margin-bottom: 1rem;
}

.field-label-strong {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
}

.empty-state--compact {
    padding-block: 1rem;
}

.medicine-dialog-grid {
    gap: 1rem;
    margin-top: 0.5rem;
}

.selection-empty-copy {
    padding: 0.75rem;
    font-size: 0.875rem;
    color: var(--text-color-secondary);
}

.permutations-section--spaced {
    margin-top: 1rem;
}

.permutations-section__label {
    display: block;
    margin-bottom: 0.5rem;
}

.dialog-footer-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    margin-top: 1.25rem;
    padding-top: 0.75rem;
    border-top: 1px solid var(--surface-border);
}

.text-success {
    color: var(--p-green-500);
}

/* Product Family variations */
.medicine-variations-section {
    padding-top: 0.5rem;
}

.badge-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: color-mix(
        in srgb,
        var(--p-primary-500) 12%,
        var(--surface-100)
    );
    color: var(--p-primary-700);
    padding: 0.2rem 0.6rem;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 700;
}

.variations-grid {
    display: grid;
    gap: 0.75rem;
    grid-template-columns: repeat(auto-fill, minmax(14rem, 1fr));
}

.variation-card {
    padding: 0.75rem;
    border: 1px solid var(--surface-border);
    background: var(--surface-card);
    transition:
        border-color 0.15s ease,
        transform 0.1s ease;
}

.variation-card:hover:not(.is-selected-card) {
    border-color: var(--p-primary-400);
}

.variation-card.is-selected-card {
    border-color: var(--p-primary-500);
    background: color-mix(
        in srgb,
        var(--p-primary-500) 4%,
        var(--surface-card)
    );
    box-shadow: 0 0 0 1px var(--p-primary-500);
}

.variation-card-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.variation-avatar {
    background: var(--surface-200);
    color: var(--text-color-secondary);
    font-weight: 700;
    width: 2rem !important;
    height: 2rem !important;
    border-radius: 50% !important;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    flex-shrink: 0;
}

.variation-avatar.select-avatar {
    background: var(--p-primary-500) !important;
    color: white !important;
}

.variation-name {
    font-size: 0.85rem;
    color: var(--text-color);
}

.dotted-card {
    border: 1px dashed var(--p-primary-400);
    background: transparent;
    display: flex;
    justify-content: center;
    align-items: center;
}

.dotted-card:hover {
    background: color-mix(in srgb, var(--p-primary-500) 4%, transparent);
}

/* Configuration grid & list */
.config-grid {
    display: grid;
    gap: 0.75rem;
    grid-template-columns: repeat(auto-fill, minmax(12rem, 1fr));
}
.config-item-card {
    background: var(--surface-card);
    border: 1px solid var(--surface-border);
    padding: 0.75rem 1rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: border-color 0.15s ease;
}
.config-item-card:hover {
    border-color: var(--p-primary-400);
}
.config-delete-btn {
    width: auto !important;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 3rem 1rem;
    background: var(--surface-card);
}
.empty-state h3 {
    margin: 0.75rem 0 0.25rem;
    font-weight: 700;
}
.empty-state p {
    color: var(--text-secondary-color);
    margin: 0;
}
.border-none {
    border: none !important;
}

/* Dialog styling */
.dialog-form-container {
    padding: 0.5rem 0;
}
.dialog-label {
    display: block;
    font-weight: 700;
    margin-bottom: 0.5rem;
    color: var(--text-color);
}
.grid-2-col {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
}
.selection-box {
    border: 1px solid var(--surface-border);
    background: var(--surface-card);
    padding: 1rem;
    display: flex;
    flex-direction: column;
}
.checkbox-list {
    max-height: 12rem;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    border: 1px solid var(--surface-border);
    background: var(--surface-ground);
    padding: 0.5rem;
}
.checkbox-tile {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.5rem 0.75rem;
    border: 1px solid transparent;
    transition: all 0.15s ease;
}
.checkbox-tile:hover {
    background: var(--surface-hover);
}
.checkbox-tile.is-selected {
    background: color-mix(in srgb, var(--p-primary-500) 12%, transparent);
    border-color: var(--p-primary-500);
}
.tile-label {
    flex-grow: 1;
    font-weight: 600;
    user-select: none;
}

/* Permutations Generator */
.permutations-section {
    border-top: 1px solid var(--surface-border);
    padding-top: 1.25rem;
}
.permutations-container {
    border: 1px solid var(--surface-border);
    max-height: 15rem;
    overflow-y: auto;
    background: var(--surface-ground);
}
.permutation-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 1rem;
    border-bottom: 1px solid var(--surface-border);
}
.permutation-row:last-child {
    border-bottom: none;
}
.permutation-info {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 700;
}
.perm-potency {
    color: var(--p-primary-500);
}
.perm-divider {
    color: var(--text-color-secondary);
}
.perm-size {
    color: var(--text-color);
}
.permutation-qty {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.qty-field-label {
    font-weight: 600;
    font-size: 0.9rem;
    color: var(--text-color-secondary);
}
.perm-qty-input {
    width: 5rem;
}

/* Utility helpers (this project does not load PrimeFlex, so the
   layout/spacing/typography utilities used in the markup are defined here,
   matching the convention used by the Patients admin page). */
.flex {
    display: flex;
}
.flex-column {
    flex-direction: column;
}
.align-items-center {
    align-items: center;
}
.align-items-start {
    align-items: flex-start;
}
.justify-content-between {
    justify-content: space-between;
}
.justify-content-center {
    justify-content: center;
}
.justify-content-end {
    justify-content: flex-end;
}

.gap-2 {
    gap: 0.5rem;
}
.gap-3 {
    gap: 1rem;
}
.gap-4 {
    gap: 1.5rem;
}

.block {
    display: block;
}
.shrink-0 {
    flex-shrink: 0;
}
.text-right {
    text-align: right;
}

.text-xs {
    font-size: 0.72rem;
}
.text-sm {
    font-size: 0.85rem;
}
.text-xl {
    font-size: 1.25rem;
}
.text-3xl {
    font-size: 1.75rem;
}
.text-5xl {
    font-size: 3rem;
}

.font-semibold {
    font-weight: 600;
}
.font-bold {
    font-weight: 700;
}

.text-primary {
    color: var(--p-primary-500);
}
.text-secondary {
    color: var(--text-color-secondary);
}
.text-danger {
    color: var(--p-red-500, #ef4444);
}
.text-400 {
    color: var(--surface-400, #9ca3af);
}
.text-500 {
    color: var(--surface-500, #6b7280);
}

.mr-1 {
    margin-right: 0.25rem;
}
.mr-2 {
    margin-right: 0.5rem;
}
.mb-1 {
    margin-bottom: 0.25rem;
}
.mb-2 {
    margin-bottom: 0.5rem;
}
.mb-3 {
    margin-bottom: 1rem;
}
.mb-4 {
    margin-bottom: 1.5rem;
}
.mt-1 {
    margin-top: 0.25rem;
}
.mt-2 {
    margin-top: 0.5rem;
}
.mt-3 {
    margin-top: 1rem;
}
.mt-4 {
    margin-top: 1.5rem;
}
.mt-5 {
    margin-top: 2rem;
}

.px-4 {
    padding-left: 1.5rem;
    padding-right: 1.5rem;
}
.pb-4 {
    padding-bottom: 1.5rem;
}
.pt-3 {
    padding-top: 1rem;
}
.py-2 {
    padding-top: 0.5rem;
    padding-bottom: 0.5rem;
}
.py-3 {
    padding-top: 1rem;
    padding-bottom: 1rem;
}
.py-4 {
    padding-top: 1.5rem;
    padding-bottom: 1.5rem;
}
.py-8 {
    padding-top: 3rem;
    padding-bottom: 3rem;
}

/* Stacked label/value spacing in the details grid */
.detail-label {
    margin-bottom: 0.2rem;
    line-height: 1.3;
}

/* Custom Overrides */
.flat-btn {
    border-radius: 6px !important;
}

.w-auto {
    width: auto !important;
}

.w-full {
    width: 100% !important;
}

.cursor-pointer {
    cursor: pointer;
}

.selection-placeholder {
    min-height: 25rem;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    text-align: center;
    gap: 0.5rem;
}

.selection-placeholder i {
    font-size: 4rem;
    color: var(--p-primary-500);
    opacity: 0.45;
    margin-bottom: 1rem;
}

.border-top {
    border-top: 1px solid var(--surface-border);
}

.content-split {
    display: grid;
    gap: 1.5rem;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1.5fr);
}

@media (max-width: 1024px) {
    .medicines-split-view {
        grid-template-columns: 1fr;
    }

    .content-split {
        grid-template-columns: 1fr;
    }

    .inventory-stats-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 768px) {
    .grid-2-col {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 480px) {
    .inventory-stats-grid {
        grid-template-columns: 1fr;
    }
}

/* Sakai Theme Rounded Style overrides */
:deep(.p-inputtext),
:deep(.p-inputnumber),
:deep(.p-inputnumber-input),
:deep(.p-inputnumber-button),
:deep(.p-button),
:deep(.p-checkbox-box),
:deep(.p-dialog),
:deep(.p-selectbutton),
:deep(.p-togglebutton),
:deep(.p-datatable),
:deep(.p-tag),
.page-card,
.inventory-card,
.variation-card,
.config-item-card,
.checkbox-tile,
.checkbox-list,
.permutations-container,
.permutation-row,
.flat-btn,
.badge-tag,
.badge-count,
.inventory-filter-count {
    border-radius: 6px !important;
}
</style>
