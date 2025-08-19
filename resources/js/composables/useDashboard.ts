import { api } from '@/lib/api';

export interface DashboardSummary {
  totals: { projects: number; activeAnalyses: number; criticalFindings: number };
  sentiment: { avg: number|null; delta: number|null };
  securityTrend: Array<{ t:string; critical:number; high:number; medium:number }>;
  communitySentiment: { avg:number|null; delta:number|null; counts:{positive:number;neutral:number;negative:number} };
  riskMatrix: number[][]; // 5x5 or []
  networkStatus: { items: Array<{ chain:string; label:string; latencyMs:number|null; requests:number|null; status:'Active'|'Idle'|'Down' }> };
  apiUsage: { totalRequests:number; successRate:number|null; avgResponseMs:number|null; rateLimitPct:number|null };
  recentProjects: Array<{ id:string; name:string; sentiment:number|null; findings:number }>;
  insights: Array<{ title:string; body:string }>;
  criticalTable: Array<{ id:string; contract:string; severity:string; cvss:number|null; impact:string }>;
  realtime: { active:number; analysesToday:number; avgTimeSec:number; findingsToday:number; systemLoadPct:number };
  lastUpdated: string;
}

export async function getDashboardSummary(): Promise<DashboardSummary> {
  const ts = Date.now();
  const s = await api.get<DashboardSummary>(`/api/dashboard/summary?ts=${ts}`);
  
  // Coerce: if any field missing, default to safe zeros/nulls; never fake.
  return {
    totals: { 
      projects: s?.totals?.projects ?? 0, 
      activeAnalyses: s?.totals?.activeAnalyses ?? 0, 
      criticalFindings: s?.totals?.criticalFindings ?? 0 
    },
    sentiment: { 
      avg: s?.sentiment?.avg ?? null, 
      delta: s?.sentiment?.delta ?? null 
    },
    securityTrend: Array.isArray(s?.securityTrend) ? s.securityTrend : [],
    communitySentiment: {
      avg: s?.communitySentiment?.avg ?? null,
      delta: s?.communitySentiment?.delta ?? null,
      counts: { 
        positive: s?.communitySentiment?.counts?.positive ?? 0, 
        neutral: s?.communitySentiment?.counts?.neutral ?? 0, 
        negative: s?.communitySentiment?.counts?.negative ?? 0 
      }
    },
    riskMatrix: Array.isArray(s?.riskMatrix) ? s.riskMatrix : [],
    networkStatus: { 
      items: Array.isArray(s?.networkStatus?.items) ? s.networkStatus.items : [] 
    },
    apiUsage: { 
      totalRequests: s?.apiUsage?.totalRequests ?? 0, 
      successRate: s?.apiUsage?.successRate ?? null, 
      avgResponseMs: s?.apiUsage?.avgResponseMs ?? null, 
      rateLimitPct: s?.apiUsage?.rateLimitPct ?? null 
    },
    recentProjects: Array.isArray(s?.recentProjects) ? s.recentProjects : [],
    insights: Array.isArray(s?.insights) ? s.insights : [],
    criticalTable: Array.isArray(s?.criticalTable) ? s.criticalTable : [],
    realtime: {
      active: s?.realtime?.active ?? 0,
      analysesToday: s?.realtime?.analysesToday ?? 0,
      avgTimeSec: s?.realtime?.avgTimeSec ?? 0,
      findingsToday: s?.realtime?.findingsToday ?? 0,
      systemLoadPct: s?.realtime?.systemLoadPct ?? 0,
    },
    lastUpdated: s?.lastUpdated ?? ''
  };
}