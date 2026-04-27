<!-- @fallthroughAttributes true -->
<!-- @strictTemplates true -->

<script setup>
import { cn } from "@/lib/utils"

const props = defineProps()
</script>

<template>
  <option data-slot="native-select-option" :class="cn('bg-popover text-popover-foreground', props.class)">
    <slot />
  </option>
</template>
