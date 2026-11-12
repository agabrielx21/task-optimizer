<script setup lang="ts">
import type { OptimizeRequest } from '#shared/types/api'
import type { SaveState } from '~/utils/inputEditor'
import { parseInput, toPrettyJson } from '~/utils/inputEditor'

const props = defineProps<{
  defaultInput: OptimizeRequest
  isMock: boolean
  saveState: SaveState
}>()

const emit = defineEmits<{ save: [input: OptimizeRequest] }>()

const input = defineModel<OptimizeRequest>({ required: true })

const employeesText = ref(toPrettyJson(input.value.employees))
const tasksText = ref(toPrettyJson(input.value.tasks))
const errors = ref<string[]>([])

const isDirty = computed(() =>
  employeesText.value !== toPrettyJson(input.value.employees) || tasksText.value !== toPrettyJson(input.value.tasks),
)

const differsFromSaved = computed(() =>
  employeesText.value !== toPrettyJson(props.defaultInput.employees) || tasksText.value !== toPrettyJson(props.defaultInput.tasks),
)

// Saving overwrites files, so it takes a second click to confirm.
const confirming = ref(false)

// Keep the text in sync when the input changes from outside (e.g. after a save).
watch(input, (next) => {
  employeesText.value = toPrettyJson(next.employees)
  tasksText.value = toPrettyJson(next.tasks)
})

function askToSave() {
  const parsed = parseInput(employeesText.value, tasksText.value)
  if (!parsed.ok) {
    errors.value = parsed.errors
    return
  }
  errors.value = []
  confirming.value = true
}

function confirmSave() {
  const parsed = parseInput(employeesText.value, tasksText.value)
  confirming.value = false
  if (parsed.ok) emit('save', parsed.input)
}

function apply() {
  const parsed = parseInput(employeesText.value, tasksText.value)
  if (!parsed.ok) {
    errors.value = parsed.errors
    return
  }
  errors.value = []
  input.value = parsed.input
}

function load(next: OptimizeRequest) {
  employeesText.value = toPrettyJson(next.employees)
  tasksText.value = toPrettyJson(next.tasks)
  apply()
}

function resetToDefault() {
  load(props.defaultInput)
}

function loadInfeasibleExample() {
  const maxSkill = Math.max(...props.defaultInput.employees.map(e => e.skillLevel))
  const nextId = Math.max(...props.defaultInput.tasks.map(t => t.taskId)) + 1
  load({
    employees: props.defaultInput.employees,
    tasks: [...props.defaultInput.tasks, { taskId: nextId, skillLevel: maxSkill + 1, estimation: 2 }],
  })
}

async function readFile(event: Event, target: 'employees' | 'tasks') {
  const file = (event.target as HTMLInputElement).files?.[0]
  if (!file) return
  const text = await file.text()
  if (target === 'employees') employeesText.value = text
  else tasksText.value = text
  ;(event.target as HTMLInputElement).value = ''
}
</script>

<template>
  <details class="group rounded-xl border border-slate-200 bg-white">
    <summary class="flex cursor-pointer list-none items-center justify-between px-4 py-3">
      <span class="font-semibold text-slate-900">Input data</span>
      <span class="text-sm text-slate-500">
        {{ input.employees.length }} {{ input.employees.length === 1 ? 'employee' : 'employees' }} ·
        {{ input.tasks.length }} {{ input.tasks.length === 1 ? 'task' : 'tasks' }}
        <span class="ml-2 inline-block transition-transform group-open:rotate-180">▾</span>
      </span>
    </summary>

    <div class="space-y-3 border-t border-slate-200 p-4">
      <p v-if="isMock" class="rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800">
        Mock mode is on (NUXT_USE_MOCK=true): results always come from mock-results.json, edited input is not used
        and nothing can be saved.
      </p>

      <div class="grid gap-3 lg:grid-cols-2">
        <label class="block">
          <span class="flex items-center justify-between text-sm font-medium text-slate-700">
            employees.json
            <span class="rounded-md border border-slate-300 px-2 py-0.5 text-xs font-normal text-slate-600 hover:bg-slate-50">
              Upload file
              <input type="file" accept=".json,application/json" class="sr-only" @change="readFile($event, 'employees')">
            </span>
          </span>
          <textarea
            v-model="employeesText"
            spellcheck="false"
            rows="12"
            class="mt-1 w-full rounded-md border border-slate-300 p-2 font-mono text-xs text-slate-800 focus:border-indigo-500 focus:outline-none"
          />
        </label>
        <label class="block">
          <span class="flex items-center justify-between text-sm font-medium text-slate-700">
            tasks.json
            <span class="rounded-md border border-slate-300 px-2 py-0.5 text-xs font-normal text-slate-600 hover:bg-slate-50">
              Upload file
              <input type="file" accept=".json,application/json" class="sr-only" @change="readFile($event, 'tasks')">
            </span>
          </span>
          <textarea
            v-model="tasksText"
            spellcheck="false"
            rows="12"
            class="mt-1 w-full rounded-md border border-slate-300 p-2 font-mono text-xs text-slate-800 focus:border-indigo-500 focus:outline-none"
          />
        </label>
      </div>

      <ul v-if="errors.length > 0" class="list-inside list-disc text-sm text-rose-700" role="alert">
        <li v-for="error in errors" :key="error">{{ error }}</li>
      </ul>

      <div class="flex flex-wrap gap-2">
        <button
          type="button"
          class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
          :disabled="!isDirty"
          @click="apply"
        >
          Run optimization
        </button>
        <button type="button" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50" @click="resetToDefault">
          Reset to default data
        </button>
        <button type="button" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50" @click="loadInfeasibleExample">
          Load infeasible example
        </button>
        <button
          type="button"
          class="rounded-md border border-indigo-300 px-3 py-1.5 text-sm font-medium text-indigo-700 hover:bg-indigo-50 disabled:opacity-50 sm:ml-auto"
          :disabled="isMock || !differsFromSaved || saveState.status === 'saving' || confirming"
          @click="askToSave"
        >
          Save as default data
        </button>
      </div>

      <div v-if="confirming" class="flex flex-wrap items-center gap-2 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-900" role="alert">
        <span>This overwrites <code>backend/data/employees.json</code> and <code>tasks.json</code>.</span>
        <button type="button" class="rounded-md bg-amber-600 px-3 py-1 font-medium text-white hover:bg-amber-700" @click="confirmSave">
          Overwrite files
        </button>
        <button type="button" class="rounded-md border border-amber-300 px-3 py-1 hover:bg-amber-100" @click="confirming = false">
          Cancel
        </button>
      </div>

      <p v-if="saveState.status === 'saving'" class="text-sm text-slate-500">Saving…</p>
      <p v-else-if="saveState.status === 'saved' && !differsFromSaved" class="text-sm text-emerald-700">
        Saved. These are now the default data (backend/data/*.json).
      </p>
      <div v-else-if="saveState.status === 'error'" class="text-sm text-rose-700" role="alert">
        <p>{{ saveState.message }}</p>
        <ul v-if="saveState.details?.length" class="list-inside list-disc">
          <li v-for="detail in saveState.details" :key="detail"><code>{{ detail }}</code></li>
        </ul>
      </div>
    </div>
  </details>
</template>
