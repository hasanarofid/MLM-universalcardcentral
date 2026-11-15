<script setup>
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Bell, Wallet, Users, ArrowUpRight, Award, CheckCheck, Trash2, ExternalLink } from 'lucide-vue-next';

const props = defineProps({
    allNotifications: {
        type: Array,
        required: true
    }
});

const activeFilter = ref('all');

const filterTabs = [
    { key: 'all', label: 'Semua' },
    { key: 'unread', label: 'Belum Dibaca' },
    { key: 'bonus', label: 'Bonus & Poin' },
    { key: 'network', label: 'Jaringan' },
    { key: 'finance', label: 'Keuangan' },
    { key: 'upgrade', label: 'Upgrade & Sistem' },
];

const filteredNotifications = computed(() => {
    if (activeFilter.value === 'all') return props.allNotifications;
    if (activeFilter.value === 'unread') return props.allNotifications.filter((n) => !n.is_read);
    if (activeFilter.value === 'upgrade') {
        return props.allNotifications.filter((n) => ['upgrade', 'system'].includes(n.type));
    }
    return props.allNotifications.filter((n) => n.type === activeFilter.value);
});

const unreadCount = computed(() => props.allNotifications.filter((n) => !n.is_read).length);

const getIcon = (type) => {
    switch (type) {
        case 'bonus': return Wallet;
        case 'network': return Users;
        case 'finance': return ArrowUpRight;
        case 'upgrade': return Award;
        default: return Bell;
    }
};

const getIconColor = (type) => {
    switch (type) {
        case 'bonus': return 'text-[#9A7B2C] bg-[#faf6eb] border border-[#D4AF37]/30';
        case 'network': return 'text-slate-800 bg-slate-100 border border-slate-200';
        case 'finance': return 'text-amber-700 bg-amber-50 border border-amber-200';
        case 'upgrade': return 'text-[#D4AF37] bg-[#0F172A] border border-[#D4AF37]/40';
        default: return 'text-slate-600 bg-slate-100 border border-slate-200';
    }
};

const markAllAsRead = () => {
    router.post(route('admin.notifications.read-all'), {}, { preserveScroll: true });
};

const openNotification = (item) => {
    if (!item.is_read) {
        router.post(
            route('admin.notifications.read', item.id),
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    if (item.link) router.visit(item.link);
                }
            }
        );
        return;
    }
    if (item.link) router.visit(item.link);
};

const deleteNotification = (item) => {
    router.delete(route('admin.notifications.destroy', item.id), { preserveScroll: true });
};
</script>

<template>
    <Head title="Pusat Notifikasi | Universal Card Central" />

    <AdminLayout>
        <div class="max-w-4xl mx-auto space-y-6">
            <!-- Header section -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2.5">
                        <h1 class="text-2xl font-black text-slate-900 tracking-tight">Pusat Notifikasi</h1>
                        <span
                            v-if="unreadCount > 0"
                            class="bg-[#0F172A] text-[#D4AF37] border border-[#D4AF37]/30 text-[11px] font-black px-2.5 py-0.5 rounded-full"
                        >
                            {{ unreadCount }} Belum Dibaca
                        </span>
                    </div>
                    <p class="text-sm text-slate-500 mt-1">Riwayat aktivitas bonus, jaringan, keuangan, dan pembaruan sistem secara real-time.</p>
                </div>

                <button
                    v-if="unreadCount > 0"
                    type="button"
                    @click="markAllAsRead"
                    class="bg-[#0F172A] hover:bg-slate-800 active:scale-[0.99] text-[#D4AF37] font-extrabold text-xs py-2.5 px-4 rounded-xl shadow-sm border border-[#D4AF37]/30 transition-all flex items-center justify-center gap-2 cursor-pointer shrink-0"
                >
                    <CheckCheck class="w-4 h-4" />
                    <span>Tandai Semua Dibaca</span>
                </button>
            </div>

            <!-- Filter Tabs -->
            <div class="flex flex-wrap items-center gap-2">
                <button
                    v-for="tab in filterTabs"
                    :key="tab.key"
                    type="button"
                    @click="activeFilter = tab.key"
                    :class="[
                        'px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer border',
                        activeFilter === tab.key
                            ? 'bg-[#0F172A] text-[#D4AF37] border-[#D4AF37]/40 shadow-sm'
                            : 'bg-white text-slate-600 border-slate-200 hover:border-slate-300 hover:bg-slate-50'
                    ]"
                >
                    {{ tab.label }}
                </button>
            </div>

            <!-- Notifications List -->
            <div class="bg-white border border-slate-200/90 rounded-3xl shadow-sm overflow-hidden">
                <div v-if="filteredNotifications.length > 0" class="divide-y divide-slate-100">
                    <div
                        v-for="item in filteredNotifications"
                        :key="item.id"
                        :class="[
                            'p-5 transition-colors flex items-start gap-4 group',
                            item.is_read ? 'bg-white hover:bg-slate-50/70' : 'bg-[#faf6eb]/55 hover:bg-[#faf6eb]'
                        ]"
                    >
                        <!-- Icon -->
                        <div
                            class="w-11 h-11 rounded-2xl shrink-0 flex items-center justify-center shadow-xs"
                            :class="getIconColor(item.type)"
                        >
                            <component :is="getIcon(item.type)" class="w-5 h-5" />
                        </div>

                        <!-- Content -->
                        <div class="flex-1 min-w-0 cursor-pointer" @click="openNotification(item)">
                            <div class="flex items-center justify-between gap-3 mb-1">
                                <div class="flex items-center gap-2 min-w-0">
                                    <span
                                        v-if="!item.is_read"
                                        class="w-2 h-2 rounded-full bg-[#D4AF37] shrink-0"
                                        title="Belum dibaca"
                                    ></span>
                                    <h4 class="text-sm font-bold text-slate-900 truncate">{{ item.title }}</h4>
                                </div>
                                <span class="text-[11px] font-semibold text-slate-400 whitespace-nowrap">{{ item.time }}</span>
                            </div>
                            <p class="text-sm text-slate-600 leading-relaxed">
                                {{ item.message }}
                            </p>
                            <div class="mt-2 flex items-center gap-3 text-[11px] font-bold text-[#9A7B2C]">
                                <span class="inline-flex items-center gap-1 group-hover:underline">
                                    <span>Lihat Detail</span>
                                    <ExternalLink class="w-3 h-3" />
                                </span>
                                <span v-if="item.created_at_formatted" class="text-slate-400 font-normal">
                                    {{ item.created_at_formatted }}
                                </span>
                            </div>
                        </div>

                        <!-- Delete button (Soft Delete) -->
                        <button
                            type="button"
                            @click.stop="deleteNotification(item)"
                            title="Hapus notifikasi"
                            class="p-2 rounded-xl text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors cursor-pointer shrink-0"
                        >
                            <Trash2 class="w-4 h-4" />
                        </button>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-else class="py-20 px-4 text-center">
                    <div class="w-16 h-16 rounded-3xl bg-[#faf6eb] border border-[#D4AF37]/30 flex items-center justify-center mx-auto text-[#B8922E] mb-4 shadow-inner">
                        <Bell class="w-8 h-8" />
                    </div>
                    <h3 class="text-lg font-bold text-slate-800">Tidak ada notifikasi</h3>
                    <p class="text-sm text-slate-500 mt-1 max-w-sm mx-auto">Semua aktivitas bonus, laporan keuangan, upgrade, dan jaringan Anda akan muncul di sini.</p>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
