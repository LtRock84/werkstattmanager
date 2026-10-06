export type Role = 'master' | 'tablet' | 'driver' | 'reception'
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
  vehicle_class?: 'car' | 'van'
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
  service_lines?: ServiceLine[]
  planning_days?: Allocation[]
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
  order_id: string | null
  employee_id: string | null
  action: string
  details: Record<string, unknown>
  context?: {
    order: Pick<Order, 'id' | 'title'> | null
    employee: Pick<Employee, 'name'> | null
    part: Pick<Part, 'title' | 'quantity' | 'part_number' | 'priority' | 'note'> | null
    defect: Pick<Defect, 'description' | 'category'> | null
    deadline: Pick<Deadline, 'title'> | null
  }
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

export interface ServiceDefinition extends Base {
  usage_count?: number
  seed_key: string
  title: string
  category: string
  description: string
  steps: string[]
  minutes: number | null
  time_min: number | null
  time_max: number | null
  time_source: string
  time_kind: 'research' | 'own' | 'unknown'
  unit: string
  vehicle_classes: ('car' | 'van')[]
  synonyms: string[]
  includes: string[]
  favorite: boolean
  position: number
  archived: boolean
  own_price: number | null
}
export interface ServiceLine {
  comparison?: { net_cents: number; gross_cents: number; vat: number; scope: string; material: string; unit: string; region: string; price_kind: string }
  material_euros?: number
  external_euros?: number
  service_id: string
  key: string
  title: string
  quantity: number
  minutes: number | null
  unit: string
  steps: string[]
  vehicle_class: 'car' | 'van'
}
export interface PriceObservation extends Base {
  seed_key: string
  service_key: string
  provider: string
  source: string
  city: string
  region: string
  checked_date: string
  valid_from: string | null
  valid_until: string | null
  price_kind: 'fixed' | 'from'
  scope: string
  material: string
  unit: string
  vat: number | null
  gross_cents: number | null
  vehicle_class: string
  segment: string
  eligible: boolean
  exclusion: string
  undated: boolean
}
export interface PriceGroup {
  key: string
  scope: string
  material: string
  unit: string
  vehicle_class: string
  segment: string
  price_kind: string
  region: string
  count: number
  local_count: number
  quality: 'comparison' | 'limited' | 'individual'
  mean: number | null
  median: number | null
  min: number | null
  max: number | null
  undated: boolean
  observations: PriceObservation[]
}
export interface Allocation { date: string; minutes: number }
export interface CapacityLimit { minutes: number; max_vehicles: number | null; closed: boolean }
export interface CapacityDay extends CapacityLimit {
  date: string
  used: number
  free: number
  vehicle_count: number
  percent: number
  overbooked: boolean
  override: (Base & CapacityLimit) | null
}
export interface PlanningSettings extends Base { settings: { weekdays: Record<string, CapacityLimit> } }
export interface ReceptionChecklist {
  key_received: boolean
  documents_checked: boolean
  damage_checked: boolean
  work_agreed: boolean
  final_checked: boolean
  customer_informed: boolean
  key_returned: boolean
  mileage: number | null
  condition_note: string
  callback_note: string
}
export interface Appointment extends Base {
  checklist?: ReceptionChecklist | null
  vehicle_id: string
  title: string
  customer_email: string
  arrival: string
  work_start: string | null
  pickup: string | null
  service_lines: ServiceLine[]
  minutes: number | null
  allocations: Allocation[]
  status: 'draft' | 'confirmed' | 'cancelled'
  stage: string
  note: string
  override_reason: string
  reminder: boolean
  order_id: string | null
}
export interface BookView { items: Appointment[]; days: CapacityDay[]; settings: PlanningSettings }
export interface CostProfile {
  id?: string
  version?: number
  name: string
  costs: Record<string, number>
  billable_hours: number
  profit_percent: number
  material_percent: number
  vat_percent: number
}
export interface Calculation {
  annual_cents: number
  cost_rate_cents: number
  rate_cents: number
  net_cents: number
  vat_cents: number
  gross_cents: number
  lines: { labor_cents: number; material_cents: number; net_cents: number }[]
  profile?: CostProfile
}
export interface Delivery extends Base {
  context?: { title: string; vehicle_label: string; arrival: string }
  appointment_id: string
  channel: string
  kind: string
  status: string
  attempts: number
  last_error: string
}
