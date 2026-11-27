<?php

namespace App\Http\Controllers;

use App\Services\Ai\AssistantService;
use App\Services\Ai\Datasets;
use App\Services\AiService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** AI Assistant kontekstual yang terpasang di setiap halaman fitur (panel samping). */
class AiAssistController extends Controller
{
    public function info(Request $request, AiService $ai)
    {
        $module = (string) $request->query('module', 'general');
        $module = isset(AssistantService::MODULES[$module]) ? $module : 'general';
        abort_unless(AssistantService::allowed($request->user(), $module), 403, 'AI Assistant tidak tersedia untuk peran atau halaman ini.');
        return response()->json(AssistantService::info($module) + ['enabled' => $ai->available(), 'remaining' => $ai->remaining($request->user()),
            'provider' => $ai->hasProvider() ? config('manrisk.ai.model') : 'mode lokal (tanpa kunci API)']);
    }

    public function ask(Request $request, AiService $ai)
    {
        abort_if($request->user()->role === 'auditor', 403, 'Auditor tidak menggunakan AI Assistant (§3.2).');
        $data = $request->validate([
            'module' => ['required', Rule::in(array_keys(AssistantService::MODULES))],
            'id' => ['nullable', 'integer', 'min:1'],
            'action' => ['nullable', 'string', 'max:30'],
            'question' => ['nullable', 'string', 'max:1500', 'required_without:action'],
            'history' => ['nullable', 'array', 'max:10'],
            'history.*.role' => ['required', Rule::in(['user', 'assistant'])],
            'history.*.content' => ['required', 'string', 'max:4000'],
        ]);
        abort_unless(AssistantService::allowed($request->user(), $data['module']), 403, 'AI Assistant tidak tersedia untuk peran atau halaman ini.');
        $actions = AssistantService::MODULES[$data['module']][1];
        if (!empty($data['action']) && !isset($actions[$data['action']])) {
            return response()->json(['message' => 'Aksi tidak dikenal untuk halaman ini.', 'errors' => ['action' => ['Aksi tidak dikenal.']]], 422);
        }
        if (in_array($data['module'], ['risk', 'action_plan', 'control', 'incident', 'rmi_assessment'], true) && empty($data['id'])) {
            return response()->json(['message' => 'Objek halaman tidak diketahui.', 'errors' => ['id' => ['Wajib diisi.']]], 422);
        }
        $svc = new AssistantService(new Datasets($request->user()), $ai);
        try {
            $res = $svc->ask($data['module'], $data['id'] ?? null, $data['action'] ?? null, $data['question'] ?? null, $data['history'] ?? []);
        } catch (AuthorizationException) {
            abort(403, 'Objek di luar cakupan akses Anda.');
        } catch (ModelNotFoundException) {
            abort(404);
        }
        return response()->json($res);
    }
}
