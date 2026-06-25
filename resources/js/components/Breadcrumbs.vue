<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

defineProps<{
    breadcrumbs: BreadcrumbItemType[];
}>();
</script>

<template>
    <nav
        aria-label="Breadcrumb"
        style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap"
    >
        <template v-for="(item, index) in breadcrumbs" :key="index">
            <Link
                v-if="index !== breadcrumbs.length - 1"
                :href="item.href"
                style="color: var(--text-secondary-color)"
            >
                {{ item.title }}
            </Link>
            <span v-else style="font-weight: 600">{{ item.title }}</span>
            <i
                v-if="index !== breadcrumbs.length - 1"
                class="pi pi-angle-right"
                style="font-size: 0.8rem; color: var(--text-secondary-color)"
            ></i>
        </template>
    </nav>
</template>
