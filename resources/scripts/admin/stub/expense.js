import moment from 'moment'

export default {
  expense_category_id: null,
  expense_date: moment().format('YYYY-MM-DD'),
  expense_number: '',
  amount: 100,
  notes: '',
  attachment_receipt: null,
  customer_id: '',
  currency_id: '',
  payment_method_id: '',
  receiptFiles: [],
  customFields: [],
  fields: [],
  in_use: false,
  selectedCurrency: null,
  tax: 0,
  base_tax: 0,
  tax_per_item: 'NO',
  taxes: [],
  sales_tax_type: null,
  sales_tax_address_type: null,
  discount_per_item: 'NO',
  discount: 0,
  discount_type: 'fixed',
  items: []
}
