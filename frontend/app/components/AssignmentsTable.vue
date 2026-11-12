<script setup lang="ts">
import type { AssignmentRow, EmployeeOption } from '~/utils/dashboard'
import { formatNumber } from '~/utils/dashboard'

defineProps<{
  rows: AssignmentRow[]
  options: EmployeeOption[]
  totals: { hours: number, cost: number }
}>()

const employeeFilter = defineModel<number | null>({ required: true })
</script>

<template>
  <section class="rounded-xl border border-slate-200 bg-white">
    <header class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-4 py-3">
      <h2 class="font-semibold text-slate-900">Assignments</h2>
      <label class="flex items-center gap-2 text-sm text-slate-600">
        Employee
        <select
          v-model="employeeFilter"
          class="rounded-md border border-slate-300 bg-white px-2 py-1.5 text-sm text-slate-900 focus:border-indigo-500 focus:outline-none"
        >
          <option :value="null">All employees</option>
          <option v-for="option in options" :key="option.employeeId" :value="option.employeeId">
            Employee {{ option.employeeId }} ({{ option.taskCount }} {{ option.taskCount === 1 ? 'task' : 'tasks' }})
          </option>
        </select>
      </label>
    </header>

    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
          <tr>
            <th class="px-4 py-2 font-medium">Task</th>
            <th class="px-4 py-2 font-medium">Required skill</th>
            <th class="px-4 py-2 text-right font-medium">Hours</th>
            <th class="px-4 py-2 font-medium">Employee</th>
            <th class="px-4 py-2 font-medium">Employee skill</th>
            <th class="px-4 py-2 text-right font-medium">Hourly rate</th>
            <th class="px-4 py-2 text-right font-medium">Task cost</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 tabular-nums">
          <tr v-for="row in rows" :key="row.taskId" class="hover:bg-slate-50">
            <td class="px-4 py-2 font-medium text-slate-900">#{{ row.taskId }}</td>
            <td class="px-4 py-2">{{ row.requiredSkill }}</td>
            <td class="px-4 py-2 text-right">{{ row.hours }}</td>
            <td class="px-4 py-2">{{ row.employeeId }}</td>
            <td class="px-4 py-2">{{ row.employeeSkill }}</td>
            <td class="px-4 py-2 text-right">{{ formatNumber(row.hourlyRate) }}</td>
            <td class="px-4 py-2 text-right font-medium text-slate-900">{{ formatNumber(row.cost) }}</td>
          </tr>
          <tr v-if="rows.length === 0">
            <td colspan="7" class="px-4 py-6 text-center text-slate-500">This employee has no tasks in this variant.</td>
          </tr>
        </tbody>
        <tfoot v-if="rows.length > 0" class="border-t border-slate-200 bg-slate-50 tabular-nums">
          <tr>
            <td class="px-4 py-2 font-medium text-slate-700" colspan="2">{{ rows.length }} {{ rows.length === 1 ? 'task' : 'tasks' }}</td>
            <td class="px-4 py-2 text-right font-medium text-slate-700">{{ totals.hours }}</td>
            <td colspan="3" />
            <td class="px-4 py-2 text-right font-semibold text-slate-900">{{ formatNumber(totals.cost) }}</td>
          </tr>
        </tfoot>
      </table>
    </div>
  </section>
</template>
