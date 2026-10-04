<?php
use App\Models\Patient;
use App\Support\{PatientAge,ActivityLogger};
use Flux\Flux;
use Livewire\Attributes\{Locked,Title};
use Livewire\Component;
new #[Title('بيانات المريض')] class extends Component {
    #[Locked] public ?Patient $record=null;
    public string $name=''; public string $phone=''; public string $gender=''; public string $birth_date=''; public string $notes=''; public string $age=''; public string $birth_year='';
    #[Locked] public bool $birthChanged=false;
    public function mount(?Patient $patient=null): void {
        abort_unless($patient?->exists,404); $this->authorize('update',$patient); $this->record=$patient;
        foreach(['name','phone','gender','notes'] as $key){$this->{$key}=(string)$patient->{$key};}
        $this->birth_date=$patient->birth_date?->toDateString() ?? ''; $this->age=$patient->age===null ? '' : (string)$patient->age; $this->birth_year=(string)($patient->birth_date?->year ?? '');
    }
    public function updatedAge(): void { $this->birthChanged=true; if($this->age===''){$this->birth_year='';return;} if(ctype_digit($this->age) && (int)$this->age<=130){$this->birth_year=(string)PatientAge::yearFromAge((int)$this->age,now()->year);} }
    public function updatedBirthYear(): void { $this->birthChanged=true; if($this->birth_year===''){$this->age='';return;} if(ctype_digit($this->birth_year) && (int)$this->birth_year<=now()->year && (int)$this->birth_year>=now()->year-130){$this->age=(string)PatientAge::ageFromYear((int)$this->birth_year,now()->year);} }
    public function save(): void {
        $this->authorize('update',$this->record);
        $data=$this->validate(['name'=>['nullable','string','max:255'],'phone'=>['nullable','string','max:30'],'gender'=>['nullable','in:male,female'],'notes'=>['nullable','string','max:5000'],'age'=>['nullable','integer','min:0','max:130'],'birth_year'=>['nullable','integer','min:'.(now()->year-130),'max:'.now()->year]]);
        unset($data['age'],$data['birth_year']); $data=array_map(fn($v)=>$v==='' ? null:$v,$data); $data['name']=trim((string)$data['name']) ?: 'بيانات المريض غير مكتملة'; $data['identity_incomplete']=trim($this->name)==='';
        if($this->birthChanged){$data['birth_date']=$this->birth_year ? $this->birth_year.'-01-01':null; $data['birth_year_only']=(bool)$this->birth_year;}
        \Illuminate\Support\Facades\DB::transaction(function() use($data){$before=$this->record->only(array_keys($data));$this->record->update($data);ActivityLogger::log('patient.updated',$this->record,['before'=>$before,'after'=>$data]);});
        Flux::toast(text:'تم حفظ بيانات المريض.'); $this->redirectRoute('patients.show',$this->record,navigate:true);
    }
}; ?>
<div class="mx-auto w-full max-w-3xl"><x-page-header :title="'تعديل بيانات '.$record->name" :subtitle="'رقم الملف '.$record->file_number" :back="route('patients.show',$record)" /><form wire:submit="save" class="space-y-5"><x-panel title="بيانات المريض" icon="user"><div class="grid gap-4 sm:grid-cols-2"><flux:input wire:model="name" label="الاسم (اختياري)" class="sm:col-span-2" /><flux:input wire:model="phone" label="الهاتف (اختياري)" type="tel" dir="ltr" /><flux:select wire:model="gender" label="النوع"><flux:select.option value="">لم يحدد</flux:select.option>@foreach(Patient::GENDERS as $key=>$label)<flux:select.option :value="$key">{{ $label }}</flux:select.option>@endforeach</flux:select><flux:input wire:model.live.debounce.300ms="age" label="العمر التقريبي" type="number" min="0" max="130" /><flux:input wire:model.live.debounce.300ms="birth_year" label="سنة الميلاد" type="number" :max="now()->year" :min="now()->year-130" /><flux:textarea wire:model="notes" label="ملاحظات المريض" rows="4" class="sm:col-span-2" /></div></x-panel><div class="flex justify-end"><flux:button type="submit" variant="primary">حفظ بيانات المريض</flux:button></div></form></div>
