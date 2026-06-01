import React, { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import DiffMatchPatch from 'diff-match-patch';
import api from '../axios';
import Layout from '../components/Layout';

const ENVS = ['development', 'staging', 'production'];
const STATUS_COLORS = {
  draft: 'bg-gray-100 text-gray-600',
  published: 'bg-green-100 text-green-700',
  archived: 'bg-yellow-100 text-yellow-700',
};

export default function PromptDetail() {
  const { slug } = useParams();
  const [prompt, setPrompt] = useState(null);
  const [versions, setVersions] = useState([]);
  const [environments, setEnvironments] = useState([]);
  const [tab, setTab] = useState('versions');
  const [loading, setLoading] = useState(true);

  // Diff state
  const [diffA, setDiffA] = useState('');
  const [diffB, setDiffB] = useState('');
  const [showDiff, setShowDiff] = useState(false);

  // Promote state
  const [promoteVersionId, setPromoteVersionId] = useState('');
  const [promoteEnv, setPromoteEnv] = useState('production');
  const [promoting, setPromoting] = useState(false);

  useEffect(() => {
    Promise.all([
      api.get(`/prompts/${slug}`),
      api.get(`/prompts/${slug}/versions`),
      api.get(`/prompts/${slug}/environments`),
    ]).then(([p, v, e]) => {
      setPrompt(p.data.data);
      setVersions(v.data.data);
      setEnvironments(e.data.data);
    }).finally(() => setLoading(false));
  }, [slug]);

  const updateStatus = async (versionId, status) => {
    const res = await api.patch(`/prompts/${slug}/versions/${versionId}`, { status });
    setVersions((prev) => prev.map((v) => v.id === versionId ? res.data.data : v));
  };

  const handlePromote = async (e) => {
    e.preventDefault();
    setPromoting(true);
    try {
      const res = await api.post(`/prompts/${slug}/promote`, {
        version_id: promoteVersionId,
        environment: promoteEnv,
      });
      setEnvironments((prev) => {
        const filtered = prev.filter((env) => env.environment !== promoteEnv);
        return [...filtered, res.data.data];
      });
    } finally {
      setPromoting(false);
    }
  };

  const renderDiff = () => {
    const vA = versions.find((v) => v.id === diffA);
    const vB = versions.find((v) => v.id === diffB);
    if (!vA || !vB) return null;

    const dmp = new DiffMatchPatch();
    const diffs = dmp.diff_main(vA.content, vB.content);
    dmp.diff_cleanupSemantic(diffs);

    return diffs.map(([op, text], i) => {
      const cls =
        op === 1 ? 'bg-green-100 text-green-900' :
        op === -1 ? 'bg-red-100 text-red-900 line-through' :
        'text-gray-700';
      return <span key={i} className={cls}>{text}</span>;
    });
  };

  if (loading) return <Layout><p className="text-gray-500">Loading…</p></Layout>;
  if (!prompt) return <Layout><p className="text-red-500">Prompt not found.</p></Layout>;

  return (
    <Layout>
      <div className="mb-6">
        <p className="text-sm text-gray-400 font-mono">{prompt.slug}</p>
        <h1 className="text-2xl font-bold text-gray-900">{prompt.name}</h1>
        {prompt.description && <p className="text-gray-500 mt-1">{prompt.description}</p>}
        {prompt.variables?.length > 0 && (
          <div className="flex gap-1 mt-2">
            {prompt.variables.map((v) => (
              <span key={v} className="bg-gray-100 text-gray-600 text-xs rounded px-2 py-0.5 font-mono">
                {`{{${v}}}`}
              </span>
            ))}
          </div>
        )}
        <div className="flex gap-3 mt-3">
          <Link
            to={`/prompts/${slug}/versions/new`}
            className="text-sm bg-indigo-600 text-white rounded px-3 py-1.5 hover:bg-indigo-700"
          >
            New version
          </Link>
          <Link
            to={`/prompts/${slug}/tests`}
            className="text-sm border border-gray-300 text-gray-700 rounded px-3 py-1.5 hover:bg-gray-50"
          >
            Tests
          </Link>
        </div>
      </div>

      {/* Tabs */}
      <div className="border-b border-gray-200 mb-6">
        {['versions', 'environments', 'test-runs'].map((t) => (
          <button
            key={t}
            onClick={() => setTab(t)}
            className={`px-4 py-2 text-sm font-medium border-b-2 -mb-px capitalize ${
              tab === t
                ? 'border-indigo-600 text-indigo-600'
                : 'border-transparent text-gray-500 hover:text-gray-700'
            }`}
          >
            {t.replace('-', ' ')}
          </button>
        ))}
      </div>

      {/* Versions tab */}
      {tab === 'versions' && (
        <div>
          <div className="space-y-3 mb-6">
            {versions.map((v) => (
              <div key={v.id} className="bg-white border border-gray-200 rounded-lg p-4">
                <div className="flex items-center justify-between mb-2">
                  <div className="flex items-center gap-2">
                    <span className="font-mono text-sm text-gray-600">v{v.version_number}</span>
                    <span className={`text-xs rounded px-2 py-0.5 font-medium ${STATUS_COLORS[v.status]}`}>
                      {v.status}
                    </span>
                    <span className="text-xs text-gray-400">{new Date(v.created_at).toLocaleDateString()}</span>
                  </div>
                  <div className="flex gap-2">
                    {['draft', 'published', 'archived'].filter((s) => s !== v.status).map((s) => (
                      <button
                        key={s}
                        onClick={() => updateStatus(v.id, s)}
                        className="text-xs text-gray-500 hover:text-gray-800 border border-gray-200 rounded px-2 py-0.5"
                      >
                        {s}
                      </button>
                    ))}
                  </div>
                </div>
                <pre className="text-sm text-gray-700 whitespace-pre-wrap font-mono bg-gray-50 rounded p-3">
                  {v.content}
                </pre>
              </div>
            ))}
          </div>

          {/* Diff panel */}
          {versions.length >= 2 && (
            <div className="bg-white border border-gray-200 rounded-lg p-4">
              <h3 className="text-sm font-semibold text-gray-700 mb-3">Compare versions</h3>
              <div className="flex gap-3 mb-3 items-end">
                <VersionSelect label="From" versions={versions} value={diffA} onChange={setDiffA} />
                <VersionSelect label="To" versions={versions} value={diffB} onChange={setDiffB} />
                <button
                  onClick={() => setShowDiff(!!(diffA && diffB))}
                  disabled={!diffA || !diffB}
                  className="bg-gray-800 text-white text-sm rounded px-3 py-2 hover:bg-gray-900 disabled:opacity-40"
                >
                  Compare
                </button>
              </div>
              {showDiff && diffA && diffB && (
                <pre className="text-sm font-mono bg-gray-50 rounded p-3 whitespace-pre-wrap">
                  {renderDiff()}
                </pre>
              )}
            </div>
          )}
        </div>
      )}

      {/* Environments tab */}
      {tab === 'environments' && (
        <div className="space-y-4">
          <div className="grid grid-cols-3 gap-4">
            {ENVS.map((env) => {
              const assignment = environments.find((e) => e.environment === env);
              const version = versions.find((v) => v.id === assignment?.prompt_version_id);
              return (
                <div key={env} className="bg-white border border-gray-200 rounded-lg p-4">
                  <p className="text-xs font-semibold text-gray-500 uppercase mb-2">{env}</p>
                  {assignment ? (
                    <>
                      <p className="font-mono text-sm">v{version?.version_number ?? '?'}</p>
                      <p className="text-xs text-gray-400 mt-1">
                        {assignment.promoted_by && `by ${assignment.promoted_by} · `}
                        {new Date(assignment.promoted_at).toLocaleDateString()}
                      </p>
                    </>
                  ) : (
                    <p className="text-sm text-gray-400">Not promoted</p>
                  )}
                </div>
              );
            })}
          </div>

          <form onSubmit={handlePromote} className="bg-white border border-gray-200 rounded-lg p-4">
            <h3 className="text-sm font-semibold text-gray-700 mb-3">Promote a version</h3>
            <div className="flex gap-3 items-end">
              <VersionSelect label="Version" versions={versions} value={promoteVersionId} onChange={setPromoteVersionId} />
              <div>
                <label className="block text-xs text-gray-500 mb-1">Environment</label>
                <select
                  value={promoteEnv}
                  onChange={(e) => setPromoteEnv(e.target.value)}
                  className="border border-gray-300 rounded px-3 py-2 text-sm"
                >
                  {ENVS.map((env) => <option key={env}>{env}</option>)}
                </select>
              </div>
              <button
                type="submit"
                disabled={!promoteVersionId || promoting}
                className="bg-indigo-600 text-white text-sm rounded px-4 py-2 hover:bg-indigo-700 disabled:opacity-40"
              >
                {promoting ? 'Promoting…' : 'Promote'}
              </button>
            </div>
          </form>
        </div>
      )}

      {/* Test runs tab */}
      {tab === 'test-runs' && <TestRunsTab slug={slug} />}
    </Layout>
  );
}

function VersionSelect({ label, versions, value, onChange }) {
  return (
    <div>
      <label className="block text-xs text-gray-500 mb-1">{label}</label>
      <select
        value={value}
        onChange={(e) => onChange(e.target.value)}
        className="border border-gray-300 rounded px-3 py-2 text-sm"
      >
        <option value="">Select…</option>
        {versions.map((v) => (
          <option key={v.id} value={v.id}>
            v{v.version_number} ({v.status})
          </option>
        ))}
      </select>
    </div>
  );
}

const RUN_STATUS_COLORS = {
  pending: 'bg-gray-100 text-gray-600',
  running: 'bg-blue-100 text-blue-700',
  passed: 'bg-green-100 text-green-700',
  failed: 'bg-red-100 text-red-700',
  error: 'bg-orange-100 text-orange-700',
};

function TestRunsTab({ slug }) {
  const [runs, setRuns] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    api.get(`/prompts/${slug}/test-runs`).then((res) => {
      setRuns(res.data.data);
    }).finally(() => setLoading(false));
  }, [slug]);

  if (loading) return <p className="text-gray-500">Loading…</p>;
  if (runs.length === 0) return (
    <p className="text-gray-500">
      No test runs yet.{' '}
      <Link to={`/prompts/${slug}/tests`} className="text-indigo-600 hover:underline">
        Go to tests
      </Link>{' '}
      to run them.
    </p>
  );

  return (
    <div className="space-y-2">
      {runs.map((run) => (
        <div key={run.id} className="bg-white border border-gray-200 rounded-lg p-4">
          <div className="flex items-center gap-3 mb-2">
            <span className={`text-xs rounded px-2 py-0.5 font-medium ${RUN_STATUS_COLORS[run.status]}`}>
              {run.status}
            </span>
            <span className="text-xs text-gray-400 font-mono">run:{run.id.slice(0, 8)}</span>
            <span className="text-xs text-gray-400">{new Date(run.created_at).toLocaleString()}</span>
          </div>
          {run.evaluation_result?.reason && (
            <p className="text-sm text-gray-600 italic">"{run.evaluation_result.reason}"</p>
          )}
          {run.error_message && (
            <p className="text-sm text-red-600">{run.error_message}</p>
          )}
        </div>
      ))}
    </div>
  );
}
