
function renderSurveyResultsChart(data, container, formId) {
  if (!data.survey_results || data.survey_results.length === 0) return;

  const defaultChartType = data.survey_chart_type;
  const results = data.survey_results;
  const labels = data.survey_labels || { title: 'Survey Results', responses: 'Responses' };

  const totalResponses = results.reduce((sum, r) => sum + (r.total || r.count || 0), 0);

  const chartContainer = document.createElement('div');
  chartContainer.className = 'efb survey-results-container mt-4 p-3';
  chartContainer.innerHTML = `
    <div class="efb survey-results-header text-center mb-4">
      <h4 class="efb fs-5 text-darkb"><i class="efb bi-bar-chart-line me-2"></i>${labels.title}</h4>
      <p class="efb text-muted fs-7">${labels.responses}: ${totalResponses}</p>
    </div>
    <div class="efb survey-charts-wrapper" id="survey-charts-${formId}"></div>
  `;
  container.appendChild(chartContainer);

  const chartsWrapper = document.getElementById(`survey-charts-${formId}`);

  const colors = [
    'rgba(32, 42, 141, 0.8)',
    'rgba(255, 75, 147, 0.8)',
    'rgba(75, 192, 192, 0.8)',
    'rgba(255, 159, 64, 0.8)',
    'rgba(153, 102, 255, 0.8)',
    'rgba(255, 99, 132, 0.8)',
    'rgba(54, 162, 235, 0.8)',
    'rgba(255, 206, 86, 0.8)',
    'rgba(75, 192, 192, 0.8)',
    'rgba(199, 199, 199, 0.8)'
  ];

  const npsColors = {
    detractors: 'rgba(239, 68, 68, 0.8)',
    passives: 'rgba(251, 191, 36, 0.8)',
    promoters: 'rgba(34, 197, 94, 0.8)'
  };

  results.forEach((result, index) => {
    const chartId = `survey-chart-${formId}-${index}`;
    const chartDiv = document.createElement('div');
    chartDiv.className = 'efb survey-chart-item mb-4 p-3 bg-light rounded-3';

    const chartType = result.chart_type || defaultChartType || 'bar';

    if (chartType === 'stats') {
      chartDiv.innerHTML = renderStatsCard(result);
      chartsWrapper.appendChild(chartDiv);
    } else if (chartType === 'nps') {
      chartDiv.innerHTML = renderNPSCard(result, npsColors);
      chartsWrapper.appendChild(chartDiv);
      const npsChartId = `${chartId}-nps`;
      if (typeof Chart === 'undefined') {
        loadChartJS(() => createNPSChart(npsChartId, result, npsColors));
      } else {
        createNPSChart(npsChartId, result, npsColors);
      }
    } else if (chartType === 'matrix') {
      chartDiv.innerHTML = renderMatrixCard(result, colors);
      chartsWrapper.appendChild(chartDiv);
      const matrixChartId = `${chartId}-matrix`;
      if (typeof Chart === 'undefined') {
        loadChartJS(() => createMatrixChart(matrixChartId, result, colors));
      } else {
        createMatrixChart(matrixChartId, result, colors);
      }
    } else {
      let extraInfo = '';
      if (result.average !== undefined) {
        extraInfo = `<p class="efb text-muted fs-7 text-center mb-2">Average: ${result.average}</p>`;
      }
      chartDiv.innerHTML = `
        <h5 class="efb fs-6 mb-3 text-center">${result.field_name}</h5>
        ${extraInfo}
        <canvas id="${chartId}" style="max-height: 300px;"></canvas>
      `;
      chartsWrapper.appendChild(chartDiv);

      if (typeof Chart === 'undefined') {
        loadChartJS(() => createChart(chartId, defaultChartType, result, colors));
      } else {
        createChart(chartId, defaultChartType, result, colors);
      }
    }
  });
}

function renderStatsCard(result) {
  const isNumeric = result.category === 'numeric';
  let statsHtml = `
    <h5 class="efb fs-6 mb-3 text-center">${result.field_name}</h5>
    <div class="efb stats-grid d-flex flex-wrap justify-content-center gap-3">
      <div class="efb stat-item text-center p-2 bg-white rounded">
        <div class="efb fs-4 text-primary fw-bold">${result.count}</div>
        <div class="efb fs-7 text-muted">Responses</div>
      </div>
  `;

  if (isNumeric) {
    statsHtml += `
      <div class="efb stat-item text-center p-2 bg-white rounded">
        <div class="efb fs-4 text-success fw-bold">${result.average}</div>
        <div class="efb fs-7 text-muted">Average</div>
      </div>
      <div class="efb stat-item text-center p-2 bg-white rounded">
        <div class="efb fs-4 text-info fw-bold">${result.min}</div>
        <div class="efb fs-7 text-muted">Min</div>
      </div>
      <div class="efb stat-item text-center p-2 bg-white rounded">
        <div class="efb fs-4 text-warning fw-bold">${result.max}</div>
        <div class="efb fs-7 text-muted">Max</div>
      </div>
    `;
  } else {
    statsHtml += `
      <div class="efb stat-item text-center p-2 bg-white rounded">
        <div class="efb fs-4 text-info fw-bold">${result.avg_length || 0}</div>
        <div class="efb fs-7 text-muted">Avg Length</div>
      </div>
    `;
  }

  statsHtml += '</div>';
  return statsHtml;
}

function renderNPSCard(result, npsColors) {
  const npsScore = result.nps_score || 0;
  const scoreClass = npsScore >= 50 ? 'text-success' : (npsScore >= 0 ? 'text-warning' : 'text-danger');

  return `
    <h5 class="efb fs-6 mb-3 text-center">${result.field_name}</h5>
    <div class="efb nps-score-display text-center mb-3">
      <div class="efb fs-1 fw-bold ${scoreClass}">${npsScore}</div>
      <div class="efb fs-7 text-muted">Net Promoter Score</div>
    </div>
    <div class="efb nps-breakdown d-flex justify-content-around mb-3">
      <div class="efb text-center">
        <div class="efb fs-5 fw-bold" style="color: ${npsColors.detractors}">${result.detractors || 0}</div>
        <div class="efb fs-7">Detractors (0-6)</div>
      </div>
      <div class="efb text-center">
        <div class="efb fs-5 fw-bold" style="color: ${npsColors.passives}">${result.passives || 0}</div>
        <div class="efb fs-7">Passives (7-8)</div>
      </div>
      <div class="efb text-center">
        <div class="efb fs-5 fw-bold" style="color: ${npsColors.promoters}">${result.promoters || 0}</div>
        <div class="efb fs-7">Promoters (9-10)</div>
      </div>
    </div>
    <canvas id="${result.field_id}-nps" style="max-height: 200px;"></canvas>
  `;
}

function renderMatrixCard(result, colors) {
  let rowsHtml = '';
  (result.rows || []).forEach((row, i) => {
    const color = colors[i % colors.length];
    rowsHtml += `
      <div class="efb matrix-row d-flex justify-content-between align-items-center p-2 mb-2 bg-white rounded">
        <span class="efb">${row.name}</span>
        <span class="efb badge" style="background: ${color}">${row.average} avg (${row.count} responses)</span>
      </div>
    `;
  });

  return `
    <h5 class="efb fs-6 mb-3 text-center">${result.field_name}</h5>
    <div class="efb matrix-rows mb-3">${rowsHtml}</div>
    <canvas id="${result.field_id}-matrix" style="max-height: 300px;"></canvas>
  `;
}

function createNPSChart(canvasId, result, npsColors) {
  const ctx = document.getElementById(canvasId);
  if (!ctx) return;

  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: result.labels,
      datasets: [{
        label: 'Responses',
        data: result.data,
        backgroundColor: result.labels.map((_, i) => {
          if (i <= 6) return npsColors.detractors;
          if (i <= 8) return npsColors.passives;
          return npsColors.promoters;
        }),
        borderWidth: 1
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
    }
  });
}

function createMatrixChart(canvasId, result, colors) {
  const ctx = document.getElementById(canvasId);
  if (!ctx) return;

  const rows = result.rows || [];
  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: rows.map(r => r.name),
      datasets: [{
        label: 'Average Score',
        data: rows.map(r => r.average),
        backgroundColor: colors.slice(0, rows.length),
        borderWidth: 1
      }]
    },
    options: {
      indexAxis: 'y',
      responsive: true,
      plugins: { legend: { display: false } },
      scales: { x: { beginAtZero: true, max: 5 } }
    }
  });
}

function loadChartJS(callback) {
  if (typeof Chart !== 'undefined') {
    callback();
    return;
  }
  const script = document.createElement('script');
  script.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js';
  script.onload = callback;
  document.head.appendChild(script);
}

function createChart(canvasId, chartType, result, colors) {
  const ctx = document.getElementById(canvasId);
  if (!ctx) return;

  const chartConfig = {
    type: chartType === 'pie' ? 'pie' : 'bar',
    data: {
      labels: result.labels,
      datasets: [{
        label: result.field_name,
        data: result.data,
        backgroundColor: colors.slice(0, result.labels.length),
        borderColor: colors.slice(0, result.labels.length).map(c => c.replace('0.8', '1')),
        borderWidth: 1
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: true,
      plugins: {
        legend: {
          display: chartType === 'pie',
          position: 'bottom'
        },
        tooltip: {
          callbacks: {
            label: function(context) {
              const total = result.total;
              const value = context.raw;
              const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
              return `${context.label}: ${value} (${percentage}%)`;
            }
          }
        }
      },
      scales: chartType === 'bar' ? {
        y: {
          beginAtZero: true,
          ticks: {
            stepSize: 1
          }
        }
      } : {}
    }
  };

  new Chart(ctx, chartConfig);
}
