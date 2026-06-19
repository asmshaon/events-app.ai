export interface EventCard {
    id: string;
    name: string | null;
    type: string;
    status: string;
    starts_at: string | null;
    timezone: string | null;
    city: string | null;
    country: string | null;
    address: string | null;
    price: number | null;
    image_url: string;
    user: { id: number; name: string } | null;
}

export interface CityOption {
    country: string;
    city: string;
}

export interface FilterOptions {
    countries: string[];
    cities: CityOption[];
}

export interface ListingResponse {
    data: EventCard[];
    current_page: number;
    last_page: number;
    total: number;
    stats: { ms: number; bytes: number };
}
