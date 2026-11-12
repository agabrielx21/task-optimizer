<template>
  <div class="space-y-4" aria-busy="true" aria-label="Loading results">
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
      <div v-for="n in 4" :key="n" class="h-20 animate-pulse rounded-xl bg-slate-200" />
    </div>
    <div class="h-64 animate-pulse rounded-xl bg-slate-200" />
    <p class="text-sm text-slate-500">Running both optimizations…</p>
  </div>
</template>
