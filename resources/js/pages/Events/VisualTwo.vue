<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Clock, MapPin, Ticket } from '@lucide/vue';
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

// Group consecutive events (already sorted by date desc) under a date heading.
const groups = computed(() => {
    const result: { key: string; label: string; items: EventCard[] }[] = [];
    let current: { key: string; label: string; items: EventCard[] } | null =
        null;

    for (const event of rows.value) {
        const key = event.starts_at ? event.starts_at.slice(0, 10) : 'tba';

        if (!current || current.key !== key) {
            current = {
                key,
                label: formatDateHeading(event.starts_at),
                items: [],
            };
            result.push(current);
        }

        current.items.push(event);
    }

    return result;
});

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

// Group heading is keyed off the UTC date slice, so format it in UTC to match.
function formatDateHeading(iso: string | null) {
    if (!iso) {
        return 'Date to be announced';
    }

    return new Intl.DateTimeFormat(undefined, {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(new Date(iso));
}

const formatTime = (card: EventCard) => {
    if (!card.starts_at) {
        return '';
    }

    try {
        return new Intl.DateTimeFormat(undefined, {
            timeStyle: 'short',
            timeZone: card.timezone ?? undefined,
        }).format(new Date(card.starts_at));
    } catch {
        return new Intl.DateTimeFormat(undefined, {
            timeStyle: 'short',
        }).format(new Date(card.starts_at));
    }
};

const formatPrice = (price: number | null) =>
    price === null ? '' : price === 0 ? 'Free' : `$${price.toFixed(2)}`;
const locationLabel = (card: EventCard) =>
    [card.city, card.country].filter(Boolean).join(', ') || card.address || '—';

const onImageError = (event: Event) => {
    (event.target as HTMLImageElement).src =
        '/storage/events/placeholders/event-1.svg';
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
    <Head title="Events Visual 2" />

    <div class="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 sm:p-6">
        <header class="flex flex-col gap-1">
            <h1 class="text-2xl font-semibold tracking-tight">
                Events Timeline
            </h1>
            <p class="text-sm text-muted-foreground">
                {{
                    total !== null
                        ? `${total.toLocaleString()} events, newest first`
                        : 'Loading events…'
                }}
            </p>
        </header>

        <!-- Filters -->
        <form
            class="flex flex-wrap items-end gap-3 rounded-xl border bg-card p-4"
            @submit.prevent="applyFilters"
        >
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

        <!-- Timeline -->
        <div class="relative pl-6 sm:pl-8">
            <!-- vertical rail -->
            <div
                class="absolute top-2 bottom-2 left-2 w-px bg-border sm:left-3"
                aria-hidden="true"
            ></div>

            <template v-for="group in groups" :key="group.key">
                <!-- date marker -->
                <div class="relative mb-4 flex items-center gap-3">
                    <span
                        class="absolute -left-[1.35rem] flex size-3 items-center justify-center sm:-left-[1.6rem]"
                    >
                        <span
                            class="size-3 rounded-full border-2 border-primary bg-background"
                        ></span>
                    </span>
                    <h2 class="text-sm font-semibold text-foreground">
                        {{ group.label }}
                    </h2>
                    <span class="text-xs text-muted-foreground"
                        >{{ group.items.length }} event{{
                            group.items.length === 1 ? '' : 's'
                        }}</span
                    >
                </div>

                <!-- entries -->
                <Link
                    v-for="event in group.items"
                    :key="event.id"
                    :href="`/events/${event.id}`"
                    class="timeline-entry group mb-4 flex gap-4 rounded-xl border bg-card p-3 transition-all duration-300 hover:-translate-y-0.5 hover:shadow-md"
                >
                    <span
                        class="absolute -left-[1.2rem] mt-5 size-2 rounded-full bg-muted-foreground/40 sm:-left-[1.45rem]"
                        aria-hidden="true"
                    ></span>
                    <div
                        class="relative size-24 shrink-0 overflow-hidden rounded-lg bg-muted sm:size-28"
                    >
                        <img
                            :src="event.image_url"
                            :alt="event.name ?? 'Event'"
                            loading="lazy"
                            class="size-full object-cover transition-transform duration-500 group-hover:scale-105"
                            @error="onImageError"
                        />
                    </div>
                    <div class="flex min-w-0 flex-col gap-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                class="inline-flex items-center gap-1 text-sm font-medium text-muted-foreground"
                            >
                                <Clock class="size-3.5" />{{
                                    formatTime(event)
                                }}
                            </span>
                            <Badge
                                :variant="statusVariant(event.status)"
                                class="capitalize"
                                >{{ event.status.replace('_', ' ') }}</Badge
                            >
                            <span
                                class="text-xs tracking-wide text-muted-foreground uppercase"
                                >{{ event.type }}</span
                            >
                        </div>
                        <h3 class="line-clamp-1 text-base font-semibold">
                            {{ event.name ?? 'Untitled event' }}
                        </h3>
                        <div
                            class="flex items-center gap-1.5 text-sm text-muted-foreground"
                        >
                            <MapPin class="size-4 shrink-0" />
                            <span class="line-clamp-1">{{
                                locationLabel(event)
                            }}</span>
                        </div>
                        <span
                            class="mt-auto inline-flex items-center gap-1.5 text-sm font-semibold"
                        >
                            <Ticket class="size-4 text-muted-foreground" />{{
                                formatPrice(event.price)
                            }}
                        </span>
                    </div>
                </Link>
            </template>

            <!-- Initial loading skeletons -->
            <template v-if="loading && !hasLoadedOnce">
                <div
                    v-for="n in 6"
                    :key="`skeleton-${n}`"
                    class="mb-4 flex gap-4 rounded-xl border bg-card p-3"
                >
                    <Skeleton class="size-24 shrink-0 rounded-lg sm:size-28" />
                    <div class="flex flex-1 flex-col gap-2">
                        <Skeleton class="h-3 w-24" />
                        <Skeleton class="h-4 w-3/4" />
                        <Skeleton class="h-3 w-1/2" />
                    </div>
                </div>
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
.timeline-entry {
    position: relative;
    animation: entry-in 0.4s ease both;
}

@keyframes entry-in {
    from {
        opacity: 0;
        transform: translateX(12px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

@media (prefers-reduced-motion: reduce) {
    .timeline-entry {
        animation: none;
    }
}
</style>
