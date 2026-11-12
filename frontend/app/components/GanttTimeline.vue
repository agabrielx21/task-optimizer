<script setup lang="ts">
import type { GanttLane } from '~/utils/dashboard'
import { timeTicks } from '~/utils/dashboard'

const props = defineProps<{
  lanes: GanttLane[]
  makespan: number
}>()

const ticks = computed(() => timeTicks(props.makespan))
const tickPercent = (tick: number) => (props.makespan > 0 ? (tick / props.makespan) * 100 : 0)
</script>

<template>
  <section class="rounded-xl border border-slate-200 bg-white p-4">
    <header class="mb-3">
      <h2 class="font-semibold text-slate-900">Timeline</h2>
      <p class="text-xs text-slate-500">Each employee works through their tasks back to back from t = 0 (shown in task-id order).</p>
    </header>

    <div class="overflow-x-auto">
      <div class="min-w-[640px]">
        <div class="relative ml-24 mr-2 h-5 text-[11px] text-slate-400 tabular-nums">
          <span
            v-for="tick in ticks"
            :key="tick"
            class="absolute"
            :class="tick === makespan ? '-translate-x-full' : tick === 0 ? '' : '-translate-x-1/2'"
            :style="{ left: `${tickPercent(tick)}%` }"
          >{{ tick }}h</span>
        </div>

        <ul class="space-y-1.5">
          <li v-for="lane in lanes" :key="lane.employeeId" class="flex items-center text-sm">
            <span class="w-24 shrink-0" :class="lane.blocks.length === 0 ? 'text-slate-400' : 'text-slate-700'">
              Employee {{ lane.employeeId }}
            </span>
            <div class="relative mr-2 h-7 flex-1 rounded bg-slate-50">
              <span
                v-for="tick in ticks"
                :key="tick"
                class="absolute inset-y-0 border-l border-slate-200"
                :style="{ left: `${tickPercent(tick)}%` }"
              />
              <div
                v-for="block in lane.blocks"
                :key="block.taskId"
                class="absolute inset-y-0.5 flex items-center justify-center overflow-hidden rounded border text-[11px] font-medium"
                :class="lane.isCritical
                  ? 'border-rose-600 bg-rose-500 text-white'
                  : 'border-indigo-500 bg-indigo-400 text-white'"
                :style="{ left: `${block.leftPercent}%`, width: `${block.widthPercent}%` }"
                :title="`Task #${block.taskId}: ${block.start}h – ${block.end}h`"
              >
                #{{ block.taskId }}
              </div>
              <span v-if="lane.blocks.length === 0" class="absolute inset-y-0 left-2 flex items-center text-xs text-slate-400">idle</span>
            </div>
          </li>
        </ul>
      </div>
    </div>
  </section>
</template>
