<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CalendarDays, MapPin, SlidersHorizontal, Ticket } from '@lucide/vue';
import {
    computed,
    onBeforeUnmount,
    onMounted,
    reactive,
    ref,
    watch,
} from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import type { EventCard, FilterOptions, ListingResponse } from '@/types/events';

const props = defineProps<{
    statuses: string[];
    filterOptions: FilterOptions;
}>();

const filters = reactive({
    status: '',
    from: '',
    to: '',
    country: '',
    city: '',
});

const rows = ref<EventCard[]>([]);
const page = ref(0);
const lastPage = ref<number | null>(null);
const total = ref<number | null>(null);
const loading = ref(false);
const hasLoadedOnce = ref(false);

const sentinel = ref<HTMLElement | null>(null);
let observer: IntersectionObserver | null = null;

const hasMore = computed(
    () => lastPage.value === null || page.value < lastPage.value,
);

// City dropdown depends on the chosen country; show all cities when none picked.
const cityOptions = computed(() =>
    props.filterOptions.cities
        .filter((c) => !filters.country || c.country === filters.country)
        .map((c) => c.city),
);

watch(
    () => filters.country,
    () => {
        if (filters.city && !cityOptions.value.includes(filters.city)) {
            filters.city = '';
        }
    },
);

async function loadMore() {
    if (loading.value || !hasMore.value) {
        return;
    }

    loading.value = true;

    const params = new URLSearchParams({ page: String(page.value + 1) });

    if (filters.status) {
        params.set('status', filters.status);
    }

    if (filters.from) {
        params.set('from', filters.from);
    }

    if (filters.to) {
        params.set('to', filters.to);
    }

    if (filters.country) {
        params.set('country', filters.country);
    }

    if (filters.city) {
        params.set('city', filters.city);
    }

    try {
        const response = await fetch(`/events/data?${params.toString()}`, {
            headers: { Accept: 'application/json' },
        });
        const payload: ListingResponse = await response.json();

        rows.value.push(...payload.data);
        page.value = payload.current_page;
        lastPage.value = payload.last_page;
        total.value = payload.total;
        hasLoadedOnce.value = true;
    } finally {
        loading.value = false;
    }
}

function applyFilters() {
    rows.value = [];
    page.value = 0;
    lastPage.value = null;
    total.value = null;
    hasLoadedOnce.value = false;
    loadMore();
}

const statusVariant = (status: string) => {
    switch (status) {
        case 'published':
            return 'default';
        case 'cancelled':
            return 'destructive';
        case 'sold_out':
            return 'secondary';
        default:
            return 'outline';
    }
};

const formatDate = (card: EventCard) => {
    if (!card.starts_at) {
        return 'Date TBA';
    }

    try {
        return new Intl.DateTimeFormat(undefined, {
            dateStyle: 'medium',
            timeStyle: 'short',
            timeZone: card.timezone ?? undefined,
        }).format(new Date(card.starts_at));
    } catch {
        return new Intl.DateTimeFormat(undefined, {
            dateStyle: 'medium',
            timeStyle: 'short',
        }).format(new Date(card.starts_at));
    }
};

const formatPrice = (price: number | null) => {
    if (price === null) {
        return '';
    }

    return price === 0 ? 'Free' : `$${price.toFixed(2)}`;
};

const locationLabel = (card: EventCard) =>
    [card.city, card.country].filter(Boolean).join(', ') || card.address || '—';

const onImageError = (event: Event) => {
    const el = event.target as HTMLImageElement;
    el.src = '/storage/events/placeholders/event-1.svg';
};

onMounted(() => {
    observer = new IntersectionObserver(
        (entries) => {
            if (entries[0]?.isIntersecting) {
                loadMore();
            }
        },
        { rootMargin: '500px' },
    );

    if (sentinel.value) {
        observer.observe(sentinel.value);
    }

    loadMore();
});

onBeforeUnmount(() => observer?.disconnect());
</script>

<template>
    <Head title="Events Visual 1" />

    <div class="flex flex-col gap-6 p-4 sm:p-6">
        <header class="flex flex-col gap-1">
            <h1 class="text-2xl font-semibold tracking-tight">
                Discover Events
            </h1>
            <p class="text-sm text-muted-foreground">
                {{
                    total !== null
                        ? `${total.toLocaleString()} events`
                        : 'Loading events…'
                }}
            </p>
        </header>

        <!-- Filters -->
        <form
            class="flex flex-wrap items-end gap-3 rounded-xl border bg-card p-4"
            @submit.prevent="applyFilters"
        >
            <div
                class="flex items-center gap-2 text-sm font-medium text-muted-foreground"
            >
                <SlidersHorizontal class="size-4" />
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-xs text-muted-foreground" for="status"
                    >Status</label
                >
                <select
                    id="status"
                    v-model="filters.status"
                    class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                >
                    <option value="">All</option>
                    <option v-for="s in statuses" :key="s" :value="s">
                        {{ s }}
                    </option>
                </select>
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-xs text-muted-foreground" for="from"
                    >From</label
                >
                <input
                    id="from"
                    v-model="filters.from"
                    type="date"
                    class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                />
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-xs text-muted-foreground" for="to">To</label>
                <input
                    id="to"
                    v-model="filters.to"
                    type="date"
                    class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                />
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-xs text-muted-foreground" for="country"
                    >Country</label
                >
                <select
                    id="country"
                    v-model="filters.country"
                    class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                >
                    <option value="">All</option>
                    <option
                        v-for="c in filterOptions.countries"
                        :key="c"
                        :value="c"
                    >
                        {{ c }}
                    </option>
                </select>
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-xs text-muted-foreground" for="city"
                    >City</label
                >
                <select
                    id="city"
                    v-model="filters.city"
                    class="h-9 min-w-40 rounded-md border border-input bg-background px-3 text-sm"
                >
                    <option value="">All</option>
                    <option v-for="c in cityOptions" :key="c" :value="c">
                        {{ c }}
                    </option>
                </select>
            </div>
            <Button type="submit">Apply</Button>
        </form>

        <!-- Grid -->
        <div
            class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
        >
            <Link
                v-for="(event, i) in rows"
                :key="event.id"
                :href="`/events/${event.id}`"
                class="event-card group"
                :style="{ animationDelay: `${(i % 50) * 25}ms` }"
            >
                <Card
                    class="h-full overflow-hidden pt-0 transition-all duration-300 group-hover:-translate-y-1 group-hover:shadow-lg"
                >
                    <div class="relative aspect-video overflow-hidden bg-muted">
                        <img
                            :src="event.image_url"
                            :alt="event.name ?? 'Event'"
                            loading="lazy"
                            class="size-full object-cover transition-transform duration-500 group-hover:scale-105"
                            @error="onImageError"
                        />
                        <Badge
                            :variant="statusVariant(event.status)"
                            class="absolute top-2 right-2 capitalize"
                        >
                            {{ event.status.replace('_', ' ') }}
                        </Badge>
                    </div>
                    <CardContent class="flex flex-col gap-2">
                        <span
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >{{ event.type }}</span
                        >
                        <h3
                            class="line-clamp-2 text-base leading-snug font-semibold"
                        >
                            {{ event.name ?? 'Untitled event' }}
                        </h3>
                        <div
                            class="flex items-center gap-1.5 text-sm text-muted-foreground"
                        >
                            <CalendarDays class="size-4 shrink-0" />
                            <span>{{ formatDate(event) }}</span>
                        </div>
                        <div
                            class="flex items-center gap-1.5 text-sm text-muted-foreground"
                        >
                            <MapPin class="size-4 shrink-0" />
                            <span class="line-clamp-1">{{
                                locationLabel(event)
                            }}</span>
                        </div>
                    </CardContent>
                    <CardFooter class="mt-auto justify-between">
                        <span
                            class="inline-flex items-center gap-1.5 text-sm font-semibold"
                        >
                            <Ticket class="size-4 text-muted-foreground" />
                            {{ formatPrice(event.price) }}
                        </span>
                        <span class="text-sm text-primary group-hover:underline"
                            >View →</span
                        >
                    </CardFooter>
                </Card>
            </Link>

            <!-- Initial loading skeletons -->
            <template v-if="loading && !hasLoadedOnce">
                <Card
                    v-for="n in 8"
                    :key="`skeleton-${n}`"
                    class="overflow-hidden pt-0"
                >
                    <Skeleton class="aspect-video w-full rounded-none" />
                    <CardContent class="flex flex-col gap-2">
                        <Skeleton class="h-3 w-16" />
                        <Skeleton class="h-4 w-3/4" />
                        <Skeleton class="h-3 w-1/2" />
                        <Skeleton class="h-3 w-2/3" />
                    </CardContent>
                </Card>
            </template>
        </div>

        <!-- Empty state -->
        <div
            v-if="!loading && hasLoadedOnce && rows.length === 0"
            class="rounded-xl border border-dashed py-16 text-center text-muted-foreground"
        >
            No events match your filters.
        </div>

        <!-- Infinite-scroll sentinel + status -->
        <div ref="sentinel" class="h-px"></div>
        <div class="py-2 text-center text-sm text-muted-foreground">
            <span v-if="loading && hasLoadedOnce">Loading more…</span>
            <span v-else-if="hasLoadedOnce && !hasMore && rows.length > 0"
                >You've reached the end.</span
            >
        </div>
    </div>
</template>

<style scoped>
.event-card {
    animation: card-in 0.4s ease both;
}

@keyframes card-in {
    from {
        opacity: 0;
        transform: translateY(12px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@media (prefers-reduced-motion: reduce) {
    .event-card {
        animation: none;
    }
}
</style>
