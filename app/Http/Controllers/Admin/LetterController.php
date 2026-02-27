<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Letter;
use App\Models\LetterTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Carbon\Carbon;

class LetterController extends Controller
{
    public function index()
    {
        $letters = Letter::query()
            ->with(['template', 'creator', 'approver'])
            ->latest()
            ->paginate(10);

        return Inertia::render('Admin/Letter/Index', [
            'letters' => $letters,
        ]);
    }

    public function create()
    {
        $templates = LetterTemplate::all();
        return Inertia::render('Admin/Letter/Create', [
            'templates' => $templates,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'letter_template_id' => 'required|exists:letter_templates,id',
            'content_data' => 'required|array',
        ]);

        $letter = Letter::create([
            'letter_template_id' => $validated['letter_template_id'],
            'content_data' => $validated['content_data'],
            'status' => 'draft',
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('admin.letters.show', $letter->id)
            ->with('success', 'Letter draft created successfully.');
    }

    public function show(Letter $letter)
    {
        $letter->load(['template', 'creator', 'approver', 'signers.user']);

        return Inertia::render('Admin/Letter/Show', [
            'letter' => $letter,
        ]);
    }

    public function edit(Letter $letter)
    {
        if ($letter->status !== 'draft') {
            return redirect()->back()->with('error', 'Only drafts can be edited.');
        }

        $letter->load('template');

        return Inertia::render('Admin/Letter/Edit', [
            'letter' => $letter,
            'template' => $letter->template,
        ]);
    }

    public function update(Request $request, Letter $letter)
    {
        if ($letter->status !== 'draft') {
            return redirect()->back()->with('error', 'Only drafts can be edited.');
        }

        $validated = $request->validate([
            'content_data' => 'required|array',
        ]);

        $letter->update([
            'content_data' => $validated['content_data'],
        ]);

        return redirect()->route('admin.letters.show', $letter->id)
            ->with('success', 'Letter draft updated successfully.');
    }

    public function approve(Letter $letter)
    {
        // TODO: Add permission check (e.g. can('letter.approve'))

        if ($letter->status !== 'draft' && $letter->status !== 'pending_review') {
            return redirect()->back()->with('error', 'Letter cannot be approved.');
        }

        $year = date('Y');
        $month = date('n'); // 1-12
        $romanMonth = $this->toRoman($month);

        // Generate Letter Number: 001/MJ-GEREJA/II/2026
        // This is a naive implementation, ideally use a sequence table or lock
        $count = Letter::whereYear('created_at', $year)->whereNotNull('letter_number')->count() + 1;
        $number = sprintf("%03d/MJ-GEREJA/%s/%s", $count, $romanMonth, $year);

        // Generate Security Hash
        $rawData = $number . json_encode($letter->content_data) . now()->timestamp . env('APP_KEY');
        $hash = hash('sha256', $rawData);

        $letter->update([
            'status' => 'approved',
            'letter_number' => $number,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'security_hash' => $hash,
        ]);

        return redirect()->back()->with('success', 'Letter approved and published.');
    }

    public function print(Letter $letter, \App\Services\QrCodeService $qrCodeService)
    {
        if ($letter->status !== 'approved') {
        // Optional: allow printing drafts with watermark
        }

        $letter->load(['template', 'signers.user']);

        $qrCode = null;
        if ($letter->security_hash) {
            // Generate QR Code pointing to verification URL
            $url = route('letter.verify', $letter->id) . '?hash=' . $letter->security_hash;
            $qrCode = $qrCodeService->generateSvg($url, 100);
        }

        return view('letters.print', [
            'letter' => $letter,
            'qrCode' => $qrCode,
        ]);
    }

    private function toRoman($number)
    {
        $map = array('M' => 1000, 'CM' => 900, 'D' => 500, 'CD' => 400, 'C' => 100, 'XC' => 90, 'L' => 50, 'XL' => 40, 'X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1);
        $returnValue = '';
        while ($number > 0) {
            foreach ($map as $roman => $int) {
                if ($number >= $int) {
                    $number -= $int;
                    $returnValue .= $roman;
                    break;
                }
            }
        }
        return $returnValue;
    }

    public function verify(Request $request, Letter $letter)
    {
        // Public verification page
        $hash = $request->query('hash');
        $isValid = $letter->status === 'approved';

        if ($letter->security_hash && $hash && $hash !== $letter->security_hash) {
            $isValid = false;
        }

        if ($isValid) {
            $letter->load('template');
        }

        return Inertia::render('Frontend/Letter/Verify', [
            'letter' => array_merge(
            $letter->only(['id', 'letter_number', 'status', 'approved_at', 'security_hash']),
            ['rendered_content' => $isValid ? $letter->rendered_content : null]
        ),
            'isValid' => $isValid,
        ]);
    }
}
