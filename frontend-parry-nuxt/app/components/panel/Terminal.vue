<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount } from 'vue'

const { onEvent } = useTerminal()

const messages = ref<string[]>([])
let unhook: (() => void) | undefined

onMounted(() => {
	unhook = onEvent((data: any) => {
		messages.value.push(data.message)
	})
})

onBeforeUnmount(() => {
	if (unhook) unhook()
})
</script>

<template>
	<Panel label="Terminal" icon="pixelarticons:script" class="terminal">
		<ul>
			<li v-for="(message, i) in messages.slice().reverse()" :key="i">
				{{ message }}
			</li>
		</ul>
	</Panel>
</template>

<style lang="scss">
@use "@/assets/style/components/panelTerminal";
</style>