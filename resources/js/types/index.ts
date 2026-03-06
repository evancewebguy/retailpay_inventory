export * from './auth';
export * from './navigation';
export * from './ui';


export interface User {
    id: number
    name: string
    email: string
    roles: string[]
    permissions: string[]
    branch?: {
        id: number
        name: string
        code: string
    }
    store?: {
        id: number
        name: string
        code: string
    }
}

export interface Product {
    id: number
    sku: string
    name: string
    barcode: string
    description: string
    cost_price: number
    selling_price: number
    unit: string
    reorder_level: number
    is_active: boolean
}

export interface Store {
    id: number
    branch_id: number
    name: string
    code: string
    location: string
    is_active: boolean
    branch?: Branch
}

export interface Branch {
    id: number
    name: string
    code: string
    address: string
    phone: string
    is_active: boolean
}

export interface Inventory {
    id: number
    store_id: number
    product_id: number
    quantity: number
    reserved_quantity: number
    available_quantity: number
    reorder_point: number
    product?: Product
    store?: Store
}

export interface Sale {
    id: number
    sale_number: string
    store_id: number
    customer_id: number | null
    total_amount: number
    discount_amount: number
    tax_amount: number
    grand_total: number
    payment_status: 'PENDING' | 'PAID' | 'PARTIAL' | 'REFUNDED'
    status: 'COMPLETED' | 'PENDING' | 'CANCELLED'
    created_at: string
    items?: SaleItem[]
    store?: Store
}

export interface SaleItem {
    id: number
    sale_id: number
    product_id: number
    quantity: number
    unit_price: number
    discount: number
    total: number
    product?: Product
}

export interface Transfer {
    id: number
    transfer_number: string
    from_store_id: number
    to_store_id: number
    status: 'PENDING' | 'PROCESSING' | 'SHIPPED' | 'RECEIVED' | 'CANCELLED'
    expected_delivery_date: string | null
    notes: string | null
    created_at: string
    items?: TransferItem[]
    from_store?: Store
    to_store?: Store
}

export interface TransferItem {
    id: number
    transfer_id: number
    product_id: number
    quantity_requested: number
    quantity_shipped: number | null
    quantity_received: number | null
    status: 'PENDING' | 'SHIPPED' | 'RECEIVED' | 'PARTIAL'
    product?: Product
}

export interface InventoryMovement {
    id: number
    reference_number: string
    movement_type: 'SALE' | 'TRANSFER' | 'ADJUSTMENT' | 'PROCUREMENT' | 'RETURN' | 'DAMAGE' | 'LOST'
    product_id: number
    from_store_id: number | null
    to_store_id: number | null
    quantity: number
    previous_quantity: number | null
    new_quantity: number | null
    created_at: string
    product?: Product
    from_store?: Store
    to_store?: Store
}