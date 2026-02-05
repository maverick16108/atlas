<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend,
  Filler
} from 'chart.js'
import { Line } from 'vue-chartjs'

ChartJS.register(
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend,
  Filler
)

const { t } = useI18n()

const labels = ['2014', '2015', '2016', '2017', '2018', '2019', '2020', '2021', '2022', '2023', '2024', '2025']

// Growth Data in %
// Gold: High growth
// Dollar: Moderate
// Stocks: Volatile
// Real Estate: Steady but lower than gold
const growthData = {
  gold: [0, 45, 80, 60, 90, 140, 260, 240, 230, 450, 520, 590], 
  dollar: [0, 80, 110, 95, 105, 110, 130, 135, 150, 220, 240, 250], 
  stocks: [0, 15, 40, 30, 60, 110, 160, 220, 60, 180, 240, 290], 
  real_estate: [0, 5, 8, 10, 25, 45, 60, 90, 130, 160, 210, 245] 
}

const stats = {
  gold: '+590%',
  dollar: '+250%',
  stocks: '+290%',
  re: '+245%'
}

const chartData = computed(() => {
  return {
    labels: labels,
    datasets: [
      {
        label: t('chart.gold'),
        data: growthData.gold,
        borderColor: '#FFE082', // Light Gold
        backgroundColor: (context) => {
          const ctx = context.chart.ctx;
          const gradient = ctx.createLinearGradient(0, 0, 0, 400);
          gradient.addColorStop(0, 'rgba(255, 224, 130, 0.4)');
          gradient.addColorStop(1, 'rgba(255, 224, 130, 0)');
          return gradient;
        },
        borderWidth: 4,
        pointRadius: 4,
        pointBackgroundColor: '#FFE082',
        pointBorderColor: '#FFE082', 
        pointHoverRadius: 8,
        pointHoverBackgroundColor: '#FFF',
        pointHoverBorderColor: '#FFE082',
        pointHoverBorderWidth: 3,
        tension: 0.4,
        fill: true,
        order: 1
      },
      {
        label: t('chart.dollar'),
        data: growthData.dollar,
        borderColor: '#4ADE80', // Green
        borderWidth: 3,
        pointRadius: 0,
        pointHoverRadius: 6,
        pointHoverBackgroundColor: '#4ADE80',
        pointHoverBorderColor: '#FFF',
        tension: 0.4,
        order: 2
      },
      {
        label: t('chart.stocks'),
        data: growthData.stocks,
        borderColor: '#C084FC', // Purple
        borderWidth: 3,
        pointRadius: 0,
        pointHoverRadius: 6,
        pointHoverBackgroundColor: '#C084FC',
        pointHoverBorderColor: '#FFF',
        tension: 0.4,
        order: 3
      },
      {
        label: t('chart.real_estate'),
        data: growthData.real_estate,
        borderColor: '#9CA3AF', // Gray/Silver (Updated from Sienna to match reference)
        borderWidth: 3,
        pointRadius: 0,
        pointHoverRadius: 6,
        pointHoverBackgroundColor: '#9CA3AF',
        pointHoverBorderColor: '#FFF',
        tension: 0.4,
        order: 4
      }
    ]
  }
})

// Custom Tooltip
// Custom Tooltip
const externalTooltipHandler = (context) => {
  const { chart, tooltip } = context;
  let tooltipEl = document.getElementById('chartjs-tooltip');

  if (!tooltipEl) {
    tooltipEl = document.createElement('div');
    tooltipEl.id = 'chartjs-tooltip';
    tooltipEl.style.position = 'absolute';
    tooltipEl.style.pointerEvents = 'none';
    tooltipEl.style.zIndex = 100;
    tooltipEl.style.transition = 'all .1s ease';
    document.body.appendChild(tooltipEl);
  }

  // Always apply styles to ensure transparency (fixes HMR/Caching issues)
  tooltipEl.style.backgroundColor = 'rgba(10, 10, 10, 0.5)';
  tooltipEl.style.backdropFilter = 'blur(16px)';
  tooltipEl.style.webkitBackdropFilter = 'blur(16px)';
  tooltipEl.className = 'p-5 rounded-xl border border-white/10 shadow-[0_20px_50px_rgba(0,0,0,0.9)] min-w-[260px] transform transition-all duration-200';

  if (tooltip.opacity === 0) {
    tooltipEl.style.opacity = 0;
    return;
  }

  if (tooltip.body) {
    const titleLines = tooltip.title || [];
    const bodyLines = tooltip.body.map(b => b.lines);

    // Header: Year
    let innerHtml = '<h4 class="text-[#D4AF37] font-kanit font-bold text-2xl mb-4 text-center tracking-widest">' + titleLines[0] + '</h4>';
    innerHtml += '<div class="h-px bg-white/10 w-full mb-4"></div>';

    // Body: Items
    bodyLines.forEach((body, i) => {
      const text = body[0].split(':'); 
      const label = text[0].trim();
      let value = text[1].trim() + '%'; 

      // Match colors to reference
      let colorClass = 'text-gray-400';
      let dotColor = 'bg-gray-500';
      
      if (label.includes('Gold') || label.includes('Золото')) {
          colorClass = 'text-[#FFE082] font-bold text-lg';
          dotColor = 'bg-[#FFE082] shadow-[0_0_10px_rgba(255,224,130,0.6)]';
      } else if (label.includes('Dollar') || label.includes('Доллар')) {
          colorClass = 'text-[#4ADE80] font-bold';
          dotColor = 'bg-[#4ADE80]';
      } else if (label.includes('Stock') || label.includes('Акций')) {
          colorClass = 'text-[#C084FC] font-bold';
          dotColor = 'bg-[#C084FC]';
      } else if (label.includes('Estate') || label.includes('Недвижимость')) {
          colorClass = 'text-[#9CA3AF] font-bold'; // Silver
          dotColor = 'bg-[#9CA3AF]';
      }

      const icon = '<div class="w-2.5 h-2.5 rounded-full ' + dotColor + ' mr-3 flex-shrink-0"></div>';
      
      innerHtml += '<div class="flex items-center justify-between py-1.5 gap-6">' + 
                      '<div class="flex items-center truncate">' + icon + '<span class="truncate ' + colorClass + ' font-kanit tracking-wide">' + label + '</span></div>' + 
                      '<div class="whitespace-nowrap font-mono ' + colorClass + '">' + value + '</div>' + 
                   '</div>';
    });

    tooltipEl.innerHTML = innerHtml;
  }

  const { offsetLeft: positionX, offsetTop: positionY } = chart.canvas;
  const canvasRect = chart.canvas.getBoundingClientRect();

  // Position: Next to points (Reference suggests to the side or top)
  // We use caretX/Y. Standard tooltip puts caret at point.
  // We place it slightly to the right of the caret to be "next to" it.
  
  let left = canvasRect.left + window.scrollX + tooltip.caretX + 20; // +20px right
  let top = canvasRect.top + window.scrollY + tooltip.caretY + 40; // Shifted Lower (was just caretY)

  // Collision detection (Simple flip if too close to right edge)
  if (left + 260 > window.innerWidth) {
      left = canvasRect.left + window.scrollX + tooltip.caretX - 280; // Flip to left
  }

  tooltipEl.style.opacity = 1;
  tooltipEl.style.left = left + 'px';
  tooltipEl.style.top = top + 'px';
  tooltipEl.style.transform = 'translateY(-20%)'; // Less vertical centering (effectively lower)
};

const chartOptions = computed(() => {
  return {
    responsive: true,
    maintainAspectRatio: false,
    animation: {
      duration: 2500,
      easing: 'easeOutQuart',
      delay: (context) => {
        let delay = 0;
        if (context.type === 'data' && context.mode === 'default') {
          delay = context.dataIndex * 50 + context.datasetIndex * 200;
        }
        return delay;
      },
    },
    interaction: {
      mode: 'index',
      intersect: false,
    },
    plugins: {
      legend: { display: false }, 
      tooltip: {
        enabled: false, 
        external: externalTooltipHandler
      }
    },
    scales: {
      x: {
        grid: { color: 'rgba(255, 255, 255, 0.05)' },
        ticks: { color: '#9CA3AF', font: { family: 'Kanit' } }
      },
      y: {
        position: 'right', 
        grid: { color: 'rgba(255, 255, 255, 0.05)' },
        ticks: { 
          display: true,
          color: '#6B7280',
          font: { family: 'Kanit' },
          callback: function(value) {
            return (value > 0 ? '+' : '') + value + '%'; 
          }
        } 
      }
    }
  }
})
</script>

<template>
  <section class="py-24 relative bg-dark-900 border-t border-white/5 overflow-hidden">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
      
      <!-- Header & Controls -->
      <div class="flex flex-col md:flex-row items-center justify-between mb-12">
        <div class="text-center md:text-left mb-8 md:mb-0" v-motion-slide-visible-once-bottom>
          <h2 class="text-3xl sm:text-4xl font-kanit font-bold text-white uppercase tracking-widest inline-flex items-center gap-3">
              <span class="w-1.5 h-8 bg-gold-500 rounded-full shadow-[0_0_15px_rgba(212,175,55,0.8)]"></span>
              {{ t('chart.title') }}
          </h2>
          <p class="text-gray-400 mt-2">{{ t('chart.subtitle') }}</p>
        </div>
      </div>

      <!-- Chart Container -->
      <div 
        class="relative h-[450px] w-full bg-gradient-to-b from-white/5 to-transparent backdrop-blur-sm rounded-2xl border border-gold-500/20 p-4 sm:p-8 shadow-[0_0_50px_rgba(0,0,0,0.5)] mb-10 group"
        v-motion
        :initial="{ opacity: 0, scale: 0.95, y: 50 }"
        :visibleOnce="{ opacity: 1, scale: 1, y: 0, transition: { duration: 1000, type: 'spring' } }"
      >
         <!-- Glow Behind -->
         <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-3/4 h-3/4 bg-gold-500/5 blur-[120px] rounded-full pointer-events-none group-hover:bg-gold-500/10 transition-colors duration-700"></div>
         
         <Line :data="chartData" :options="chartOptions" />
      </div>

      <!-- Glamorous Legend/Stats Cards -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
          <!-- Gold Card -->
          <div class="relative group p-6 rounded-xl bg-gradient-to-br from-dark-800 to-dark-900 border border-gold-500/50 hover:border-gold-500 hover:-translate-y-1 transition-all duration-300 cursor-pointer overflow-hidden shadow-[0_0_30px_rgba(212,175,55,0.15)] hover:shadow-[0_0_50px_rgba(212,175,55,0.4)]">
              <div class="absolute inset-0 bg-gold-500/5 group-hover:bg-gold-500/20 transition-colors duration-500"></div>
              <div class="absolute top-0 left-0 w-full h-[1px] bg-gradient-to-r from-transparent via-gold-500 to-transparent opacity-50 group-hover:opacity-100 transition-opacity"></div>
              
              <div class="relative z-10 text-center">
                  <div class="text-4xl font-oswald font-bold text-[#FFE082] mb-1 drop-shadow-[0_0_10px_rgba(255,224,130,0.5)]">
                      {{ stats.gold }}
                  </div>
                  <div class="h-px w-12 bg-gold-500/50 mx-auto my-3 group-hover:w-24 group-hover:bg-gold-400 transition-all duration-500 shadow-[0_0_10px_rgba(212,175,55,0.8)]"></div>
                  <h3 class="text-white font-kanit font-bold uppercase tracking-wider text-sm group-hover:text-gold-200 transition-colors text-glow-gold">
                      {{ t('chart.gold') }}
                  </h3>
                  <span class="text-gold-500/60 text-xs mt-1 block group-hover:text-gold-500 transition-colors">10 Years Growth</span>
              </div>
          </div>

          <!-- Dollar Card -->
          <div class="relative group p-6 rounded-xl bg-white/5 border border-white/10 hover:border-green-400 hover:-translate-y-1 transition-all duration-300 cursor-pointer shadow-[0_0_30px_rgba(74,222,128,0.1)] hover:shadow-[0_0_50px_rgba(74,222,128,0.3)]">
              <div class="relative z-10 text-center">
                  <div class="text-3xl font-oswald font-bold text-green-400 mb-1 drop-shadow-[0_0_8px_rgba(74,222,128,0.6)]">
                      {{ stats.dollar }}
                  </div>
                  <div class="h-px w-12 bg-white/10 mx-auto my-3 group-hover:bg-green-400 transition-colors shadow-[0_0_8px_rgba(74,222,128,0.6)]"></div>
                  <h3 class="text-gray-400 font-kanit font-bold uppercase tracking-wider text-sm group-hover:text-green-200 transition-colors text-glow-green">
                      {{ t('chart.dollar') }}
                  </h3>
              </div>
          </div>

          <!-- Real Estate Card -->
          <div class="relative group p-6 rounded-xl bg-white/5 border border-white/10 hover:border-[#9CA3AF] hover:-translate-y-1 transition-all duration-300 cursor-pointer shadow-[0_0_30px_rgba(156,163,175,0.15)] hover:shadow-[0_0_50px_rgba(156,163,175,0.35)]">
              <div class="relative z-10 text-center">
                  <div class="text-3xl font-oswald font-bold text-[#9CA3AF] mb-1 drop-shadow-[0_0_8px_rgba(156,163,175,0.6)]">
                      {{ stats.re }}
                  </div>
                  <div class="h-px w-12 bg-white/10 mx-auto my-3 group-hover:bg-[#9CA3AF] transition-colors shadow-[0_0_8px_rgba(156,163,175,0.6)]"></div>
                  <h3 class="text-gray-400 font-kanit font-bold uppercase tracking-wider text-sm group-hover:text-gray-200 transition-colors text-glow-gray">
                      {{ t('chart.real_estate') }}
                  </h3>
              </div>
          </div>

          <!-- Stocks Card -->
          <div class="relative group p-6 rounded-xl bg-white/5 border border-white/10 hover:border-purple-400 hover:-translate-y-1 transition-all duration-300 cursor-pointer shadow-[0_0_30px_rgba(192,132,252,0.15)] hover:shadow-[0_0_50px_rgba(192,132,252,0.35)]">
              <div class="relative z-10 text-center">
                  <div class="text-3xl font-oswald font-bold text-purple-400 mb-1 drop-shadow-[0_0_8px_rgba(192,132,252,0.6)]">
                      {{ stats.stocks }}
                  </div>
                  <div class="h-px w-12 bg-white/10 mx-auto my-3 group-hover:bg-purple-400 transition-colors shadow-[0_0_8px_rgba(192,132,252,0.6)]"></div>
                  <h3 class="text-gray-400 font-kanit font-bold uppercase tracking-wider text-sm group-hover:text-purple-200 transition-colors text-glow-purple">
                      {{ t('chart.stocks') }}
                  </h3>
                  <span class="text-gray-600 text-xs mt-1 block group-hover:text-purple-500 transition-colors">High Volatility</span>
              </div>
          </div>
      </div>
    </div>
  </section>
</template>
