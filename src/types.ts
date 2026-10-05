export type Role = 'master' | 'tablet' | 'driver'
export interface Base {
  id: string
  version: number
  created_at: number
  updated_at: number
}
export interface Customer extends Base {
  name: string
  contact: string
  email: string
  phone: string
  notes: string
  archived: boolean
}
export interface Vehicle extends Base {
  customer_id: string | null
  kind: 'fleet' | 'customer'
  plate: string
  number: string
  vin: string
  make: string
  model: string
  registration: string | null
  mileage: number | null
  mileage_date: string | null
  notes: string
  archived: boolean
}
export interface Employee extends Base {
  name: string
  archived: boolean
}
export interface Template extends Base {
  title: string
  description: string
  steps: string[]
  minutes: number | null
  favorite: boolean
  position: number
  archived: boolean
}
export interface Snippet extends Base {
  title: string
  text: string
  favorite: boolean
  position: number
  archived: boolean
}
export type OrderStatus =
  'draft' | 'open' | 'active' | 'paused' | 'review' | 'completed' | 'cancelled'
export interface Order extends Base {
  vehicle_id: string
  last_employee_id: string | null
  title: string
  description: string
  steps: string[]
  minutes: number | null
  planned_start: string | null
  due_at: string | null
  priority: 'low' | 'normal' | 'urgent'
  status: OrderStatus
  pause_reason: string | null
  completed_at: string | null
}
export interface Interval extends Base {
  order_id: string
  employee_id: string
  started: number
  ended: number | null
  note: string
  steps: string[]
}
export interface Active extends Base {
  order_id: string
  employee_id: string
  interval_id: string
}
export interface Part extends Base {
  order_id: string
  employee_id: string
  title: string
  quantity: number
  part_number: string
  priority: string
  note: string
  status: 'requested' | 'ordered' | 'received' | 'rejected' | 'cancelled'
}
export interface Defect extends Base {
  vehicle_id: string
  order_id: string | null
  duplicate_id: string | null
  category: string
  description: string
  priority: string
  reporter: string
  mileage: number | null
  mileage_confirmed: boolean
  status: 'new' | 'open' | 'resolved' | 'duplicate' | 'dismissed'
  public_text: string
  published: boolean
}
export interface Deadline extends Base {
  vehicle_id: string
  title: string
  due_date: string | null
  due_km: number | null
  repeat_months: number | null
  repeat_km: number | null
  warning_days: number[]
  status: string
  completed_date: string | null
}
export interface Attachment extends Base {
  name: string
  mime: string
  size: number
  vehicle_id: string
  order_id: string | null
  defect_id: string | null
  part_id: string | null
  ready?: boolean
}
export interface Audit extends Base {
  actor: string
  employee_id: string | null
  action: string
  details: Record<string, unknown>
}
export interface Mileage extends Base {
  vehicle_id: string
  value: number
  date: string
  source: string
}
export interface FailedNotification {
  id: string
  version: number
  created_at: number
  recipient: string
  subject: string
  attempts: number
}
export interface Tables {
  customers: Customer
  vehicles: Vehicle
  employees: Employee
  templates: Template
  snippets: Snippet
  orders: Order
  intervals: Interval
  active: Active
  parts: Part
  defects: Defect
  deadlines: Deadline
  attachments: Attachment
  audit: Audit
  mileage: Mileage
  outbox: FailedNotification
}
export type Table = keyof Tables
export type Editable =
  'customers' | 'vehicles' | 'employees' | 'templates' | 'snippets' | 'deadlines'
export type Row = Tables[Table]
export interface Page<T> {
  items: T[]
  more: boolean
  offset: number
}
export interface DriverView {
  vehicle: Pick<Vehicle, 'plate' | 'number' | 'make' | 'model'>
  items: { text: string; status: string; created_at: number }[]
  more: boolean
}
