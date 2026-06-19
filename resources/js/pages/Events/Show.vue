<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { CalendarDays, MapPin, Ticket } from '@lucide/vue';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface EventImage {
    id: number;
    path: string;
    sort_order: number;
}

interface EventDetail {
    id: string;
    type: string;
    status: string;
    starts_at: string | null;
    ends_at: string | null;
    timezone: string | null;
    city: string | null;
    country: string | null;
    address: string | null;
    images: EventImage[];
    payload: {
        name?: string;
        description?: string;
        venue?: { name?: string; capacity?: number | string };
        pricing?: { currency?: string; min_price?: number | string };
        tags?: string[];
    };
}

const props = defineProps<{ event: EventDetail }>();

const imageUrls = computed(() =>
    props.event.images.length
        ? props.event.images.map((i) => `/storage/${i.path}`)
        : ['/storage/events/placeholders/event-1.svg'],
);

const heroImage = computed(() => imageUrls.value[0]);

const name = computed(() => props.event.payload.name ?? 'Untitled event');
const description = computed(() => props.event.payload.description ?? '');

const location = computed(
    () =>
        [props.event.city, props.event.country].filter(Boolean).join(', ') ||
        props.event.address ||
        'Location to be announced',
);

const when = computed(() => {
    if (!props.event.starts_at) {
        return 'Date to be announced';
    }

    try {
        return new Intl.DateTimeFormat(undefined, {
            dateStyle: 'full',
            timeStyle: 'short',
            timeZone: props.event.timezone ?? undefined,
        }).format(new Date(props.event.starts_at));
    } catch {
        return new Intl.DateTimeFormat(undefined, {
            dateStyle: 'full',
            timeStyle: 'short',
        }).format(new Date(props.event.starts_at));
    }
});

const price = computed(() => {
    const raw = props.event.payload.pricing?.min_price;

    if (raw === undefined || raw === null) {
        return '';
    }

    const value = Number(raw);

    return value === 0 ? 'Free' : `$${value.toFixed(2)}`;
});

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

const form = useForm({ name: '', email: '' });

const submit = () =>
    form.post(`/events/${props.event.id}/rsvp`, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });

const onImageError = (e: Event) => {
    (e.target as HTMLImageElement).src =
        '/storage/events/placeholders/event-1.svg';
};
</script>

<template>
    <Head :title="name" />

    <div class="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4 sm:p-6">
        <Link
            href="/events-visual-1"
            class="text-sm text-primary hover:underline"
            >← Back to events</Link
        >

        <!-- Hero -->
        <div class="relative overflow-hidden rounded-2xl border bg-muted">
            <img
                :src="heroImage"
                :alt="name"
                class="aspect-[21/9] w-full object-cover"
                @error="onImageError"
            />
            <Badge
                :variant="statusVariant(event.status)"
                class="absolute top-3 right-3 capitalize"
                >{{ event.status.replace('_', ' ') }}</Badge
            >
        </div>

        <!-- Thumbnails -->
        <div v-if="imageUrls.length > 1" class="flex flex-wrap gap-2">
            <img
                v-for="(url, i) in imageUrls"
                :key="i"
                :src="url"
                :alt="`${name} image ${i + 1}`"
                class="size-20 rounded-lg border object-cover"
                @error="onImageError"
            />
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Details -->
            <div class="flex flex-col gap-4 lg:col-span-2">
                <div>
                    <span
                        class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >{{ event.type }}</span
                    >
                    <h1 class="mt-1 text-2xl font-semibold tracking-tight">
                        {{ name }}
                    </h1>
                </div>

                <div class="flex flex-col gap-2 text-sm">
                    <div class="flex items-center gap-2">
                        <CalendarDays class="size-4 text-muted-foreground" />
                        <span
                            >{{ when
                            }}<span
                                v-if="event.timezone"
                                class="text-muted-foreground"
                            >
                                ({{ event.timezone }})</span
                            ></span
                        >
                    </div>
                    <div class="flex items-center gap-2">
                        <MapPin class="size-4 text-muted-foreground" />
                        <span>{{ location }}</span>
                    </div>
                    <div v-if="price" class="flex items-center gap-2">
                        <Ticket class="size-4 text-muted-foreground" />
                        <span class="font-semibold">{{ price }}</span>
                    </div>
                </div>

                <p
                    v-if="description"
                    class="leading-relaxed text-muted-foreground"
                >
                    {{ description }}
                </p>

                <div
                    v-if="event.payload.tags?.length"
                    class="flex flex-wrap gap-2"
                >
                    <Badge
                        v-for="tag in event.payload.tags"
                        :key="tag"
                        variant="outline"
                        >{{ tag }}</Badge
                    >
                </div>
            </div>

            <!-- RSVP -->
            <Card class="h-fit lg:col-span-1">
                <CardHeader>
                    <CardTitle>Register interest</CardTitle>
                </CardHeader>
                <CardContent>
                    <form class="flex flex-col gap-3" @submit.prevent="submit">
                        <div class="flex flex-col gap-1.5">
                            <Label for="name">Name</Label>
                            <Input
                                id="name"
                                v-model="form.name"
                                type="text"
                                autocomplete="name"
                                required
                            />
                            <p
                                v-if="form.errors.name"
                                class="text-xs text-destructive"
                            >
                                {{ form.errors.name }}
                            </p>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <Label for="email">Email</Label>
                            <Input
                                id="email"
                                v-model="form.email"
                                type="email"
                                autocomplete="email"
                                required
                            />
                            <p
                                v-if="form.errors.email"
                                class="text-xs text-destructive"
                            >
                                {{ form.errors.email }}
                            </p>
                        </div>
                        <Button type="submit" :disabled="form.processing">
                            {{ form.processing ? 'Registering…' : 'RSVP' }}
                        </Button>
                        <p class="text-xs text-muted-foreground">
                            We'll email you a confirmation and reminders before
                            the event.
                        </p>
                    </form>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
