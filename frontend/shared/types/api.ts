
// Mirror of the backend's OptimizationResult::toArray(); checked at runtime by shared/utils/contract.ts.
export const VARIANTS = ['cost', 'makespan'] as const
export type Variant = (typeof VARIANTS)[number]

export interface Employee {
  employeeId: number
  skillLevel: number
  hourlyRate: number
}

export interface Task {
  taskId: number
  skillLevel: number
  estimation: number
}

export interface OptimizeRequest {
  employees: Employee[]
  tasks: Task[]
}

export interface Assignment {
  taskId: number
  employeeId: number
}

export interface EmployeeSummary {
  employeeId: number
  totalAssignedHours: number
}

export interface OptimizationMeta {
  isOptimal?: boolean
  lowerBound?: number
  searchNodes?: number
}

export interface OptimizationResult {
  variant: Variant
  status: 'feasible' | 'infeasible'
  assignments: Assignment[]
  totalPrice: number
  makespan: number
  employeeSummary: EmployeeSummary[]
  unassignableTaskIds: number[]
  meta: OptimizationMeta
}

export interface OptimizeResponse {
  source: 'backend' | 'mock'
  input: OptimizeRequest
  results: Record<Variant, OptimizationResult>
}

export interface DataResponse {
  source: 'backend' | 'mock'
  input: OptimizeRequest
}

export interface OptimizeErrorData {
  details?: string[]
  backendUrl?: string
}
