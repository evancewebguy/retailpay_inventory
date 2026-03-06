<template>
  <div>
    <div class="flex justify-between items-center mb-6">
      <h2 class="text-2xl font-bold text-gray-900">Inventory Management</h2>
      <div class="space-x-2">
        <button 
          @click="showAdjustmentModal = true"
          class="bg-yellow-600 text-white px-4 py-2 rounded-md hover:bg-yellow-700"
        >
          Adjust Stock
        </button>
        <button 
          @click="showMovementHistoryModal = true"
          class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700"
        >
          Movement History
        </button>
        <button 
          @click="fetchInventory"
          class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700"
        >
          Refresh
        </button>
      </div>
    </div>

    <!-- Filters -->
    <div class="bg-white p-4 rounded-lg shadow mb-6">
      <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700">Store</label>
          <select 
            v-model="filters.store_id" 
            @change="fetchInventory"
            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3"
          >
            <option value="">All Stores</option>
            <option v-for="store in stores" :key="store.id" :value="store.id">
              {{ store.name }}
            </option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Branch</label>
          <select 
            v-model="filters.branch_id" 
            @change="fetchInventory"
            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3"
          >
            <option value="">All Branches</option>
            <option v-for="branch in branches" :key="branch.id" :value="branch.id">
              {{ branch.name }}
            </option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Search</label>
          <input 
            type="text" 
            v-model="filters.search" 
            @input="debouncedSearch"
            placeholder="Product name or SKU"
            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3"
          >
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Status</label>
          <select 
            v-model="filters.low_stock" 
            @change="fetchInventory"
            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3"
          >
            <option value="">All Items</option>
            <option value="true">Low Stock Only</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Per Page</label>
          <select 
            v-model="filters.per_page" 
            @change="fetchInventory"
            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3"
          >
            <option value="20">20</option>
            <option value="50">50</option>
            <option value="100">100</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Inventory Table -->
    <div class="bg-white shadow overflow-hidden sm:rounded-md">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Store</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">SKU</th>
              <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">On Hand</th>
              <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Reserved</th>
              <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Available</th>
              <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Unit Price</th>
              <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total Value</th>
              <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
              <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-for="item in inventory" :key="item.id">
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                {{ item.store?.name }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                {{ item.product?.name }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                {{ item.product?.sku }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                {{ item.quantity }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                {{ item.reserved_quantity }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium"
                  :class="getStockStatusClass(item)">
                {{ item.available_quantity }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                ${{ item.product?.selling_price?.toFixed(2) }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                ${{ (item.quantity * (item.product?.selling_price || 0)).toFixed(2) }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-center">
                <span :class="getStockBadgeClass(item)">
                  {{ getStockStatus(item) }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                <button 
                  @click="viewProductHistory(item.product_id)"
                  class="text-indigo-600 hover:text-indigo-900 mr-2"
                  title="View History"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                  </svg>
                </button>
                <button 
                  @click="prepareAdjustment(item)"
                  class="text-yellow-600 hover:text-yellow-900"
                  title="Adjust Stock"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M5 4a1 1 0 00-2 0v7.268a2 2 0 00.555 1.386l3.473 3.474a2 2 0 002.828 0L14 12.414V15a1 1 0 102 0V9a1 1 0 00-1-1H9a1 1 0 100 2h3.586l-2.293 2.293a1 1 0 01-1.414 0L5 9.586V4z" />
                  </svg>
                </button>
              </td>
            </tr>
            <tr v-if="inventory.length === 0">
              <td colspan="10" class="px-6 py-4 text-center text-gray-500">
                No inventory items found
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Pagination -->
    <div class="mt-4 flex justify-between items-center">
      <div class="text-sm text-gray-700">
        Showing {{ (currentPage - 1) * perPage + 1 }} to 
        {{ Math.min(currentPage * perPage, total) }} of {{ total }} results
      </div>
      <div class="flex space-x-2">
        <button 
          @click="changePage(currentPage - 1)" 
          :disabled="currentPage === 1"
          class="px-3 py-1 border rounded disabled:opacity-50 hover:bg-gray-50"
        >
          Previous
        </button>
        <span class="px-3 py-1">{{ currentPage }} / {{ lastPage }}</span>
        <button 
          @click="changePage(currentPage + 1)" 
          :disabled="currentPage === lastPage"
          class="px-3 py-1 border rounded disabled:opacity-50 hover:bg-gray-50"
        >
          Next
        </button>
      </div>
    </div>

    <!-- Adjustment Modal -->
    <div v-if="showAdjustmentModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg p-6 max-w-4xl w-full max-h-screen overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-lg font-medium">Stock Adjustment</h3>
          <button @click="showAdjustmentModal = false" class="text-gray-400 hover:text-gray-600">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
        
        <form @submit.prevent="submitAdjustment">
          <div class="grid grid-cols-2 gap-4 mb-4">
            <div>
              <label class="block text-sm font-medium text-gray-700">Store *</label>
              <select 
                v-model="adjustment.store_id" 
                required
                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3"
              >
                <option value="">Select Store</option>
                <option v-for="store in stores" :key="store.id" :value="store.id">
                  {{ store.name }}
                </option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Adjustment Type *</label>
              <select 
                v-model="adjustment.type" 
                required
                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3"
              >
                <option value="">Select Type</option>
                <option value="ADDITION">Addition</option>
                <option value="REDUCTION">Reduction</option>
                <option value="CORRECTION">Correction</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Reason *</label>
              <select 
                v-model="adjustment.reason" 
                required
                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3"
              >
                <option value="">Select Reason</option>
                <option value="DAMAGE">Damage</option>
                <option value="LOST">Lost</option>
                <option value="FOUND">Found</option>
                <option value="COUNT_CORRECTION">Count Correction</option>
                <option value="EXPIRY">Expiry</option>
                <option value="OTHER">Other</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Notes</label>
              <input 
                type="text" 
                v-model="adjustment.notes"
                placeholder="Optional notes"
                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3"
              >
            </div>
          </div>

          <div class="mb-4">
            <h4 class="text-md font-medium mb-2">Items to Adjust</h4>
            <div v-for="(item, index) in adjustment.items" :key="index" class="flex space-x-2 mb-2 items-center">
              <select 
                v-model="item.product_id" 
                required
                class="flex-1 border border-gray-300 rounded-md py-2 px-3"
                @change="loadCurrentQuantity(item)"
              >
                <option value="">Select Product</option>
                <option v-for="product in products" :key="product.id" :value="product.id">
                  {{ product.name }} ({{ product.sku }})
                </option>
              </select>
              <input 
                type="number" 
                v-model="item.current_quantity" 
                readonly
                placeholder="Current"
                class="w-24 bg-gray-100 border border-gray-300 rounded-md py-2 px-3"
              >
              <input 
                type="number" 
                v-model="item.new_quantity" 
                required
                min="0"
                placeholder="New Qty"
                class="w-24 border border-gray-300 rounded-md py-2 px-3"
                @input="calculateChange(item)"
              >
              <span class="w-24 text-sm" :class="getChangeClass(item)">
                {{ item.change > 0 ? '+' : '' }}{{ item.change }}
              </span>
              <input 
                type="text" 
                v-model="item.reason"
                placeholder="Item reason"
                class="flex-1 border border-gray-300 rounded-md py-2 px-3"
              >
              <button 
                type="button" 
                @click="removeAdjustmentItem(index)"
                class="text-red-600 hover:text-red-900"
              >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
              </button>
            </div>
            <button 
              type="button" 
              @click="addAdjustmentItem"
              class="mt-2 text-indigo-600 hover:text-indigo-900"
            >
              + Add Item
            </button>
          </div>

          <div class="flex justify-end space-x-2">
            <button 
              type="button" 
              @click="showAdjustmentModal = false"
              class="px-4 py-2 border border-gray-300 rounded-md hover:bg-gray-50"
            >
              Cancel
            </button>
            <button 
              type="submit"
              :disabled="isSubmitting || adjustment.items.length === 0"
              class="px-4 py-2 bg-yellow-600 text-white rounded-md hover:bg-yellow-700 disabled:opacity-50"
            >
              {{ isSubmitting ? 'Processing...' : 'Submit Adjustment' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Movement History Modal -->
    <div v-if="showMovementHistoryModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg p-6 max-w-6xl w-full max-h-screen overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-lg font-medium">Inventory Movement History</h3>
          <button @click="showMovementHistoryModal = false" class="text-gray-400 hover:text-gray-600">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- History Filters -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
          <div>
            <label class="block text-sm font-medium text-gray-700">From Date</label>
            <input 
              type="date" 
              v-model="historyFilters.from_date"
              class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3"
            >
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">To Date</label>
            <input 
              type="date" 
              v-model="historyFilters.to_date"
              class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3"
            >
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Movement Type</label>
            <select 
              v-model="historyFilters.movement_type"
              class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3"
            >
              <option value="">All Types</option>
              <option value="SALE">Sale</option>
              <option value="TRANSFER">Transfer</option>
              <option value="ADJUSTMENT">Adjustment</option>
              <option value="PROCUREMENT">Procurement</option>
              <option value="RETURN">Return</option>
            </select>
          </div>
          <div class="flex items-end">
            <button 
              @click="fetchMovementHistory"
              class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700"
            >
              Apply Filters
            </button>
          </div>
        </div>

        <!-- History Table -->
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reference</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Quantity</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">From Store</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">To Store</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created By</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="movement in movements" :key="movement.id">
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  {{ formatDate(movement.created_at) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ movement.reference_number }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm">
                  <span :class="getMovementTypeClass(movement.movement_type)">
                    {{ movement.movement_type }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ movement.product?.name }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium"
                    :class="movement.quantity > 0 ? 'text-green-600' : 'text-red-600'">
                  {{ movement.quantity > 0 ? '+' : '' }}{{ movement.quantity }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  {{ movement.from_store?.name || '-' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  {{ movement.to_store?.name || '-' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  {{ movement.creator?.name }}
                </td>
              </tr>
              <tr v-if="movements.length === 0">
                <td colspan="8" class="px-6 py-4 text-center text-gray-500">
                  No movements found
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Low Stock Alerts -->
    <div v-if="lowStockAlerts.length > 0" class="mt-6 bg-yellow-50 border-l-4 border-yellow-400 p-4">
      <div class="flex">
        <div class="flex-shrink-0">
          <svg class="h-5 w-5 text-yellow-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
          </svg>
        </div>
        <div class="ml-3">
          <p class="text-sm text-yellow-700">
            <span class="font-bold">{{ lowStockAlerts.length }}</span> items are low on stock or out of stock
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, onMounted, watch } from 'vue';
import axios from 'axios';
import { useToast } from 'vue-toastification';
import debounce from 'lodash/debounce';

export default {
  setup() {
    const toast = useToast();
    
    // Data
    const inventory = ref([]);
    const stores = ref([]);
    const branches = ref([]);
    const products = ref([]);
    const movements = ref([]);
    const lowStockAlerts = ref([]);
    
    // Pagination
    const currentPage = ref(1);
    const lastPage = ref(1);
    const perPage = ref(20);
    const total = ref(0);
    
    // Filters
    const filters = ref({
      store_id: '',
      branch_id: '',
      search: '',
      low_stock: '',
      per_page: 20
    });
    
    // History filters
    const historyFilters = ref({
      from_date: new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
      to_date: new Date().toISOString().split('T')[0],
      movement_type: ''
    });
    
    // Modals
    const showAdjustmentModal = ref(false);
    const showMovementHistoryModal = ref(false);
    
    // Form state
    const isSubmitting = ref(false);
    const adjustment = ref({
      store_id: '',
      type: '',
      reason: '',
      notes: '',
      items: []
    });

    // Debounced search
    const debouncedSearch = debounce(() => {
      currentPage.value = 1;
      fetchInventory();
    }, 500);

    // Methods
    const fetchInventory = async () => {
      try {
        const params = {
          page: currentPage.value,
          ...filters.value
        };
        
        const response = await axios.get('/inventory', { params });
        inventory.value = response.data.data;
        currentPage.value = response.data.current_page;
        lastPage.value = response.data.last_page;
        perPage.value = response.data.per_page;
        total.value = response.data.total;
      } catch (error) {
        toast.error('Failed to fetch inventory');
      }
    };

    const fetchStores = async () => {
      try {
        const response = await axios.get('/stores');
        stores.value = response.data;
      } catch (error) {
        toast.error('Failed to fetch stores');
      }
    };

    const fetchBranches = async () => {
      try {
        const response = await axios.get('/branches');
        branches.value = response.data;
      } catch (error) {
        toast.error('Failed to fetch branches');
      }
    };

    const fetchProducts = async () => {
      try {
        const response = await axios.get('/products');
        products.value = response.data;
      } catch (error) {
        toast.error('Failed to fetch products');
      }
    };

    const fetchLowStockAlerts = async () => {
      try {
        const response = await axios.get('/inventory/low-stock');
        lowStockAlerts.value = response.data.alerts;
      } catch (error) {
        console.error('Failed to fetch low stock alerts', error);
      }
    };

    const fetchMovementHistory = async () => {
      try {
        const response = await axios.get('/inventory/movements/history', {
          params: historyFilters.value
        });
        movements.value = response.data.data;
      } catch (error) {
        toast.error('Failed to fetch movement history');
      }
    };

    const changePage = (page) => {
      if (page >= 1 && page <= lastPage.value) {
        currentPage.value = page;
        fetchInventory();
      }
    };

    const getStockStatus = (item) => {
      if (item.available_quantity <= 0) return 'Out of Stock';
      if (item.available_quantity <= item.reorder_point) return 'Low Stock';
      return 'In Stock';
    };

    const getStockStatusClass = (item) => {
      if (item.available_quantity <= 0) return 'text-red-600';
      if (item.available_quantity <= item.reorder_point) return 'text-yellow-600';
      return 'text-green-600';
    };

    const getStockBadgeClass = (item) => {
      if (item.available_quantity <= 0) {
        return 'px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800';
      }
      if (item.available_quantity <= item.reorder_point) {
        return 'px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800';
      }
      return 'px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800';
    };

    const getMovementTypeClass = (type) => {
      const classes = {
        'SALE': 'px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800',
        'TRANSFER': 'px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-purple-100 text-purple-800',
        'ADJUSTMENT': 'px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800',
        'PROCUREMENT': 'px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800',
        'RETURN': 'px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800'
      };
      return classes[type] || 'px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800';
    };

    const formatDate = (date) => {
      return new Date(date).toLocaleString();
    };

    const addAdjustmentItem = () => {
      adjustment.value.items.push({
        product_id: '',
        current_quantity: 0,
        new_quantity: 0,
        change: 0,
        reason: ''
      });
    };

    const removeAdjustmentItem = (index) => {
      if (adjustment.value.items.length > 1) {
        adjustment.value.items.splice(index, 1);
      }
    };

    const prepareAdjustment = (item) => {
      adjustment.value = {
        store_id: item.store_id,
        type: 'CORRECTION',
        reason: 'COUNT_CORRECTION',
        notes: '',
        items: [{
          product_id: item.product_id,
          current_quantity: item.quantity,
          new_quantity: item.quantity,
          change: 0,
          reason: ''
        }]
      };
      showAdjustmentModal.value = true;
    };

    const loadCurrentQuantity = async (item) => {
      if (item.product_id && adjustment.value.store_id) {
        try {
          const response = await axios.get('/inventory', {
            params: {
              store_id: adjustment.value.store_id,
              product_id: item.product_id
            }
          });
          if (response.data.data && response.data.data.length > 0) {
            item.current_quantity = response.data.data[0].quantity;
            item.new_quantity = response.data.data[0].quantity;
          } else {
            item.current_quantity = 0;
            item.new_quantity = 0;
          }
          calculateChange(item);
        } catch (error) {
          console.error('Failed to load current quantity', error);
        }
      }
    };

    const calculateChange = (item) => {
      item.change = item.new_quantity - item.current_quantity;
    };

    const getChangeClass = (item) => {
      if (item.change > 0) return 'text-green-600';
      if (item.change < 0) return 'text-red-600';
      return 'text-gray-600';
    };

    const submitAdjustment = async () => {
      isSubmitting.value = true;
      try {
        await axios.post('/inventory/adjustments', adjustment.value);
        toast.success('Stock adjustment completed successfully');
        showAdjustmentModal.value = false;
        fetchInventory();
        fetchLowStockAlerts();
        
        // Reset form
        adjustment.value = {
          store_id: '',
          type: '',
          reason: '',
          notes: '',
          items: []
        };
      } catch (error) {
        toast.error(error.response?.data?.message || 'Adjustment failed');
      } finally {
        isSubmitting.value = false;
      }
    };

    const viewProductHistory = (productId) => {
      historyFilters.value.product_id = productId;
      showMovementHistoryModal.value = true;
      fetchMovementHistory();
    };

    // Watch for filter changes
    watch(() => filters.value.store_id, () => {
      currentPage.value = 1;
      fetchInventory();
    });

    watch(() => filters.value.branch_id, () => {
      currentPage.value = 1;
      fetchInventory();
    });

    watch(() => filters.value.low_stock, () => {
      currentPage.value = 1;
      fetchInventory();
    });

    watch(() => filters.value.per_page, () => {
      currentPage.value = 1;
      fetchInventory();
    });

    // Initial load
    onMounted(() => {
      fetchInventory();
      fetchStores();
      fetchBranches();
      fetchProducts();
      fetchLowStockAlerts();
    });

    return {
      // Data
      inventory,
      stores,
      branches,
      products,
      movements,
      lowStockAlerts,
      filters,
      historyFilters,
      currentPage,
      lastPage,
      perPage,
      total,
      showAdjustmentModal,
      showMovementHistoryModal,
      adjustment,
      isSubmitting,
      
      // Methods
      debouncedSearch,
      fetchInventory,
      changePage,
      getStockStatus,
      getStockStatusClass,
      getStockBadgeClass,
      getMovementTypeClass,
      formatDate,
      addAdjustmentItem,
      removeAdjustmentItem,
      prepareAdjustment,
      loadCurrentQuantity,
      calculateChange,
      getChangeClass,
      submitAdjustment,
      viewProductHistory,
      fetchMovementHistory
    };
  }
}
</script>