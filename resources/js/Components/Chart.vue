<script setup>
import { onMounted, onUnmounted, ref, watch } from 'vue';
import * as echarts from 'echarts/core';
import { BarChart, LineChart, PieChart, RadarChart, ScatterChart } from 'echarts/charts';
import { GridComponent, TooltipComponent, LegendComponent, TitleComponent } from 'echarts/components';
import { CanvasRenderer } from 'echarts/renderers';
echarts.use([BarChart, LineChart, PieChart, RadarChart, ScatterChart, GridComponent, TooltipComponent, LegendComponent, TitleComponent, CanvasRenderer]);
const props = defineProps({ option: { type: Object, required: true }, height: { type: String, default: '260px' } });
const el = ref(null);
let chart;
const css = (v) => getComputedStyle(document.documentElement).getPropertyValue(v).trim();
const render = () => {
  if (!chart) return;
  const fg = css('--fg-2'), line = css('--line');
  chart.setOption({ textStyle: { fontFamily: css('--f-body'), color: fg }, color: ['#1268c4', '#34a8e0', '#6a3fd4', '#0d8a84', '#f7973a', '#c42a78', '#16824a'], grid: { left: 8, right: 12, top: 28, bottom: 8, containLabel: true }, tooltip: { trigger: 'axis', backgroundColor: css('--surface'), borderColor: line, textStyle: { color: css('--fg') } }, ...props.option }, true);
};
const resize = () => chart && chart.resize();
onMounted(() => { chart = echarts.init(el.value, null, { renderer: 'canvas' }); render(); window.addEventListener('resize', resize); });
onUnmounted(() => { window.removeEventListener('resize', resize); chart && chart.dispose(); });
watch(() => props.option, render, { deep: true });
</script>
<template><div ref="el" :style="{ height, width: '100%' }"></div></template>
