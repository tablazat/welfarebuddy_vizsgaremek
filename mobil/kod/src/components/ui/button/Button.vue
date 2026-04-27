<script setup>
import { Primitive } from "reka-ui"
import { cn } from "@/lib/utils"
import { buttonVariants } from "."

const props = defineProps({
  as: { default: 'button' },
  asChild: { default: undefined },
  variant: { default: undefined },
  size: { default: undefined },
  class: { default: undefined },
})
</script>

<template>
  <Primitive
    data-slot="button"
    :as="as"
    :as-child="asChild"
    :class="cn(buttonVariants({ variant, size }), props.class)"
  >
    <slot />
  </Primitive>
</template>
