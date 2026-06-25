<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';

const { isCurrentOrParentUrl } = useCurrentUrl();

const sidebarNavItems = [
    { title: 'Profile', href: editProfile() },
    { title: 'Security', href: editSecurity() },
    { title: 'Appearance', href: editAppearance() },
];
</script>

<template>
    <div class="page-card">
        <div class="section-stack" style="gap: 1.5rem">
            <Heading
                title="Settings"
                description="Manage your profile, security, and workspace preferences from one polished control center"
            />

            <div class="settings-layout">
                <aside class="settings-nav">
                    <div class="section-heading">
                        <h3>Workspace controls</h3>
                        <p>Keep your account and security settings aligned.</p>
                    </div>

                    <Link
                        v-for="item in sidebarNavItems"
                        :key="item.title"
                        :href="item.href"
                        :class="{
                            'is-active': isCurrentOrParentUrl(item.href),
                        }"
                    >
                        {{ item.title }}
                    </Link>
                </aside>

                <section class="settings-content">
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
