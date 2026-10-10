<?php
use App\Models\{Branch, Doctor, ExamType, MedicalCase, Patient, Setting, User};
use App\Support\{AccessScope, Money, PatientAge};
use App\Services\{CaseRegistration, CasePricing};
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\{Computed, Locked, Title};
use Livewire\Component;

new #[Title('بيانات الحالة')] class extends Component {
    #[Locked] public ?MedicalCase $medicalCase = null;
    public string $patientMode = 'new';
    #[Locked] public ?int $patient_id = null;
    public string $patientSearch = '';
    public array $newPatient = ['name'=>'','phone'=>'','gender'=>'','birth_year'=>'','age'=>''];
    public string $doctor_id = '';
    public string $technician_id = '';
    public string $branch_id = '';
    public string $exam_type_id = '';
    public string $exam_date = '';
    public string $case_code = '';
    public string $notes_for_doctor = '';
    public string $notes_internal = '';
    public string $base_price = '';
    public function mount(?MedicalCase $case = null): void {
        $this->authorize($case?->exists ? 'update' : 'create', $case?->exists ? $case : MedicalCase::class);
        if ($case?->exists) {
            $this->medicalCase = $case;
            $this->patient_id = $case->patient_id;
            foreach (['doctor_id','technician_id','branch_id','exam_type_id','case_code','notes_for_doctor','notes_internal'] as $key) { $this->{$key} = (string) $case->{$key}; }
            $this->exam_date = $case->exam_date->toDateString();
            $this->base_price = Money::decimal($case->final_price_minor ?? $case->base_price_minor);
        } else {
            $this->exam_date = today()->toDateString();
            $this->branch_id = (string) (auth()->user()->branch_id ?? AccessScope::branches(auth()->user())->active()->value('id'));
            if ($patient = AccessScope::patients(auth()->user())->find(request()->integer('patient'))) { $this->selectPatient($patient->id); }
            if ($doctor = AccessScope::doctors(auth()->user())->find(request()->integer('doctor'))) { $this->doctor_id = (string) $doctor->id; $this->updatedDoctorId(); }
        }
    }
    public function isEditing(): bool { return $this->medicalCase !== null; }
    #[Computed] public function patientResults() {
        return mb_strlen(trim($this->patientSearch)) < 2 ? collect() : AccessScope::patients(auth()->user())->search($this->patientSearch)->withCount('cases')->latest()->limit(8)->get();
    }
    #[Computed] public function selectedPatient(): ?Patient { return $this->patient_id ? AccessScope::patients(auth()->user())->withCount('cases')->find($this->patient_id) : null; }
    #[Computed] public function doctors() {
        return AccessScope::doctors(auth()->user())->with('user')->active()->when($this->branch_id,fn($q)=>$q->whereHas('branches',fn($b)=>$b->where('branches.id',$this->branch_id)))->orderByName()->get();
    }
    #[Computed] public function exams() {
        return ExamType::active()->whereDoesntHave('branches',fn($q)=>$q->where('branches.id',$this->branch_id)->where('branch_exam_type.is_active',false))->ordered()->get();
    }
    public function selectPatient(int $id): void {
        $patient = AccessScope::patients(auth()->user())->findOrFail($id);
        $this->patient_id = $patient->id; $this->patientMode = 'existing'; $this->patientSearch = ''; $this->resetValidation();
    }
    public function clearPatient(): void { $this->patient_id = null; $this->patientMode = 'new'; }
    public function updatedNewPatient($value, string $key): void {
        if (! in_array($key,['age','birth_year'],true)) { return; }
        if ($value === '') { $this->newPatient['age'] = ''; $this->newPatient['birth_year'] = ''; return; }
        if (! ctype_digit((string)$value)) { return; }
        if ($key === 'age' && (int)$value <= 130) { $this->newPatient['birth_year'] = (string) PatientAge::yearFromAge((int)$value,now()->year); }
        if ($key === 'birth_year' && (int)$value <= now()->year && (int)$value >= now()->year-130) { $this->newPatient['age'] = (string) PatientAge::ageFromYear((int)$value,now()->year); }
    }
    public function updatedDoctorId(): void {
        if (auth()->user()->isAdmin() && $this->doctor_id && $doctor = Doctor::find($this->doctor_id)) {
            if (! $doctor->branches()->whereKey($this->branch_id)->exists()) { $this->branch_id = (string)$doctor->branches()->value('branches.id'); }
        }
        unset($this->doctors);
    }
    public function updatedBranchId(): void { $this->doctor_id = ''; $this->technician_id = ''; $this->quote(); }
    public function updatedExamTypeId(): void { $this->quote(); }
    private function quote(): void {
        $price = ($exam = ExamType::find($this->exam_type_id)) && ($branch = AccessScope::branches(auth()->user())->find($this->branch_id)) ? CasePricing::quote($exam,$branch) : null;
        $this->base_price = Money::decimal($price);
    }
    public function save(): void {
        $this->authorize($this->isEditing() ? 'update' : 'create', $this->medicalCase ?? MedicalCase::class);
        $this->validate(['newPatient.age'=>['nullable','integer','min:0','max:130']]);
        $case = DB::transaction(function () {
            $case = CaseRegistration::save(auth()->user(), [
                'patient_id'=>$this->patientMode === 'existing' || $this->isEditing() ? $this->patient_id : null,
                'name'=>$this->newPatient['name'],'phone'=>$this->newPatient['phone'],'gender'=>$this->newPatient['gender'],'birth_year'=>$this->newPatient['birth_year'] ?: null,
                'doctor_id'=>$this->doctor_id ?: null,'technician_id'=>$this->technician_id ?: null,'branch_id'=>$this->branch_id,
                'exam_type_id'=>$this->exam_type_id ?: null,'exam_date'=>$this->exam_date,'notes_for_doctor'=>$this->notes_for_doctor,'notes_internal'=>$this->notes_internal,
                'case_code'=>auth()->user()->isAdmin() ? $this->case_code : null,
            ],$this->medicalCase);
            if ($this->base_price !== '' && Money::minor($this->base_price) !== $case->final_price_minor) {
                $price = Money::minor($this->base_price);
                CasePricing::apply(auth()->user(),$case,$price,$price,null);
            }
            return $case;
        });
        Flux::toast(variant:'success',text:'تم حفظ الحالة '.$case->case_code);
        $this->redirectRoute('cases.show',$case,navigate:true);
    }
}; ?>

<div class="mx-auto w-full max-w-4xl">
    <x-page-header :title="$this->isEditing() ? 'تعديل الحالة '.$medicalCase->case_code : 'حالة جديدة'" subtitle="سجّل ما تعرفه الآن، وأكمل بيانات الفحص والملفات لاحقًا" :back="route('cases.index')" />
    <form wire:submit="save" class="space-y-5">
        <x-panel title="المريض" icon="user">
            @if ($this->selectedPatient)
                <div class="flex items-center justify-between gap-3 rounded-lg bg-brand-50 p-4">
                    <div><div class="font-semibold">{{ $this->selectedPatient->name }}</div><div class="mt-1 text-sm text-zinc-600">{{ $this->selectedPatient->file_number }} · {{ $this->selectedPatient->cases_count }} حالة سابقة</div><a class="text-sm text-brand-700 underline" href="{{ route('patients.show',$this->selectedPatient) }}" target="_blank">فتح ملف المريض</a></div>
                    @unless ($this->isEditing())<flux:button variant="ghost" wire:click="clearPatient">تغيير المريض</flux:button>@endunless
                </div>
            @else
                <flux:input wire:model.live.debounce.300ms="patientSearch" label="البحث عن مريض مسجل" icon="magnifying-glass" placeholder="الاسم أو الهاتف أو رقم الملف" autocomplete="off" />
                @if (mb_strlen(trim($patientSearch)) >= 2)
                    <div class="my-3 divide-y overflow-hidden rounded-lg border">
                        @forelse ($this->patientResults as $result)
                            <button type="button" wire:click="selectPatient({{ $result->id }})" class="flex min-h-12 w-full justify-between gap-3 p-3 text-start hover:bg-brand-50"><span>{{ $result->name }} <small class="text-zinc-500">{{ $result->file_number }}</small></span><span class="text-sm text-zinc-500">{{ $result->cases_count }} حالة</span></button>
                        @empty <p class="p-3 text-sm text-zinc-600">لا يوجد تطابق. يمكنك تسجيل بيانات المريض أدناه.</p> @endforelse
                    </div>
                @endif
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2"><flux:input wire:model="newPatient.name" label="اسم المريض (اختياري)" /></div>
                    <div class="grid grid-cols-2 gap-4 sm:col-span-2"><flux:input wire:model.live.debounce.300ms="newPatient.age" label="العمر التقريبي" type="number" min="0" max="130" inputmode="numeric" />
                    <flux:input wire:model.live.debounce.300ms="newPatient.birth_year" label="سنة الميلاد" type="number" :max="now()->year" :min="now()->year-130" inputmode="numeric" /></div>
                    <flux:input wire:model="newPatient.phone" label="رقم الهاتف (اختياري)" type="tel" dir="ltr" />
                    <flux:select wire:model="newPatient.gender" label="النوع (اختياري)"><flux:select.option value="">لم يحدد</flux:select.option>@foreach(Patient::GENDERS as $key=>$label)<flux:select.option :value="$key">{{ $label }}</flux:select.option>@endforeach</flux:select>
                </div>
            @endif
        </x-panel>
        <x-panel title="الفحص" icon="clipboard-document-list">
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model.live="branch_id" label="الفرع" :disabled="! auth()->user()->isAdmin()">@foreach(AccessScope::branches(auth()->user())->active()->get() as $branch)<flux:select.option :value="(string)$branch->id">{{ $branch->name }}</flux:select.option>@endforeach</flux:select>
                <flux:input wire:model="exam_date" label="تاريخ الفحص" type="date" />
                <flux:select wire:model.live="exam_type_id" label="نوع الفحص"><flux:select.option value="">يحدد لاحقًا</flux:select.option>@foreach($this->exams as $exam)<flux:select.option :value="(string)$exam->id">{{ $exam->name }}</flux:select.option>@endforeach</flux:select>
                <flux:select wire:model.live="doctor_id" label="الطبيب"><flux:select.option value="">يحدد لاحقًا</flux:select.option>@foreach($this->doctors as $doctor)<flux:select.option :value="(string)$doctor->id">{{ $doctor->display_name }}</flux:select.option>@endforeach</flux:select>
                <flux:select wire:model="technician_id" label="الفني المسؤول"><flux:select.option value="">يحدد لاحقًا</flux:select.option>@foreach(User::where('role','technician')->where('is_active',true)->where('branch_id',$branch_id)->get() as $technician)<flux:select.option :value="(string)$technician->id">{{ $technician->name }}</flux:select.option>@endforeach</flux:select>
                @if($this->isEditing() && auth()->user()->isAdmin())<flux:input wire:model="case_code" label="كود الحالة" dir="ltr" />@endif
            </div>
        </x-panel>
        <details class="rounded-xl border bg-white p-5" @if($base_price !== '') open @endif><summary class="cursor-pointer font-medium">قيمة الفحص <span class="text-sm font-normal text-zinc-500">(يمكن استكمالها لاحقًا)</span></summary><div class="mt-4"><x-panel title="قيمة الفحص" icon="banknotes">
            @if(! Setting::get('currency'))<p class="mb-4 text-sm text-amber-800">يحدد المدير العملة من إعدادات المركز قبل تسجيل الأسعار. يمكن حفظ الحالة بدون سعر.</p>@endif
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="base_price" label="قيمة الفحص" inputmode="decimal" :disabled="!auth()->user()->isAdmin() && $this->isEditing() && $medicalCase->base_price_minor !== null" />
            </div>
            <flux:error name="final_price" />
            <flux:error name="price" />
        </x-panel></div></details>
        <details class="rounded-xl border bg-white p-5"><summary class="cursor-pointer font-medium">ملاحظات إضافية</summary><div class="mt-4 grid gap-4 sm:grid-cols-2"><flux:textarea wire:model="notes_for_doctor" label="ملاحظات للطبيب" rows="3" /><flux:textarea wire:model="notes_internal" label="ملاحظات داخلية" rows="3" /></div></details>
        <div class="flex justify-end gap-3"><flux:button :href="route('cases.index')" variant="ghost" wire:navigate>إلغاء</flux:button><flux:button type="submit" variant="primary" wire:loading.attr="disabled">حفظ الحالة</flux:button></div>
    </form>
</div>
