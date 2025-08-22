<?php

namespace App\Http\Controllers\Backend\Admin;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Doctor;
use App\Models\Employee;
use App\Models\JobTitle;
use App\Models\ClinicInfo;
public function addDoctor(){
        $departments = Department::all();
        $clinic = ClinicInfo::firstOrFail();

        return view('Backend.admin.doctors.add', [
            'departments' => $departments,
            'clinic'      => $clinic,
            'time_start'  => $clinic->work_start,
            'time_end'    => $clinic->work_end,
            'work_days'   => $clinic->work_days,
        ]);
    }

    public function storeDoctor(Request $request){
        if(User::where('email', $request->email)->exists()){
            return response()->json(['data' => 0]);
        }else{
            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $imageName = 'doctors/' . time() . '_' . $file->getClientOriginalName();
                $file->move(public_path('doctors'), $imageName);
            } else {
                $imageName = null;
            }

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'phone' => $request->phone,
                'address' => $request->address,
                'image' => $imageName,
                'date_of_birth' => $request->date_of_birth,
                'gender' => $request->gender,
            ]);
            $user->assignRole(['doctor']);


            $employee = Employee::create([
                'user_id' => $user->id,
                'department_id' => $request->department_id,
                'work_start_time' => $request->work_start_time,
                'work_end_time' => $request->work_end_time,
                'working_days' => $request->working_days,
                'hire_date' => now()->toDateString(),
                'status' => $request->status,
                'short_biography' => $request->short_biography,
            ]);


            $jobTitle = JobTitle::where('name', 'Doctor')->first();
            EmployeeJobTitle::create([
                'employee_id' => $employee->id,
                'job_title_id' => $jobTitle->id,
            ]);


            Doctor::create([
                'employee_id' => $employee->id,
                'qualification' => $request->qualification,
                'experience_years' => $request->experience_years,
            ]);

            return response()->json(['data' => 1]);
        }
    }

    public function viewDoctors(){
        $doctors = Doctor::orderBy('id', 'asc')->paginate(12);
        return view('Backend.admin.doctors.view' , compact('doctors'));
    }





    public function searchDoctors(Request $request){
        $keyword = trim((string) $request->input('keyword', ''));
        $filter  = $request->input('filter', '');

        $doctors = Doctor::with([
            'employee.department:id,name',
            'employee.user:id,name,address,image',
        ]);

        if ($keyword !== '') {
            switch ($filter) {
                case 'name':
                    $doctors->whereHas('employee.user', function ($q) use ($keyword) {
                        $q->where('name', 'LIKE', $keyword.'%');
                    });
                    break;

                case 'department':
                    $doctors->whereHas('employee.department', function ($q) use ($keyword) {
                        $q->where('name', 'LIKE', $keyword.'%');
                    });
                    break;

                case 'status':
                    $doctors->whereHas('employee', function ($q) use ($keyword) {
                        $q->where('status', 'LIKE', $keyword.'%');
                    });
                    break;
            }
        }

        $doctors = $doctors->orderBy('id')->paginate(12);
        $view = view('Backend.admin.doctors.searchDoctor', compact('doctors'))->render();
        $pagination = $doctors->total() > 12
            ? $doctors->links('pagination::bootstrap-4')->render()
            : '';

        return response()->json([
            'html'       => $view,
            'pagination' => $pagination,
            'count'      => $doctors->total(),
            'searching'  => $keyword !== '',
        ]);
    }

    public function profileDoctor($id){
        $doctor = Doctor::with('employee')->findOrFail($id);
        return view('Backend.admin.doctors.profile', compact('doctor'));
    }





    public function editDoctor($id){
        $doctor = Doctor::findOrFail($id);
        $employee = Employee::findOrFail($doctor->employee_id);
        $user = User::findOrFail($employee->user_id);

        $departments   = Department::all();
        $working_days  = $doctor->employee->working_days ?? [];

        // بيانات العيادة
        $clinic = ClinicInfo::firstOrFail();

        return view('Backend.admin.doctors.edit', [
            'doctor'       => $doctor,
            'employee'     => $employee,
            'user'         => $user,
            'departments'  => $departments,
            'working_days' => $working_days,
            'clinic'       => $clinic,
            'time_start'   => $clinic->work_start,
            'time_end'     => $clinic->work_end,
            'work_days'    => $clinic->work_days, // Array من جدول العيادة
        ]);
    }
    public function updateDoctor(Request $request, $id){
        $doctor = Doctor::findOrFail($id);
        $employee = Employee::where('id', $doctor->employee_id)->first();
        $user = $employee ? User::where('id', $employee->user_id)->first() : null;

        $currentUserId = $doctor->employee->user_id;
        if (User::where('email', $request->email)->where('id', '!=', $currentUserId)->exists()) {
            return response()->json(['data' => 0]);
        }else{
            $imageName = $user->image;
            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $imageName = 'doctors/' . time() . '_' . $file->getClientOriginalName();
                $file->move(public_path('doctors'), $imageName);
            }

            $password = $user->password;
            if ($request->filled('password')) {
                $password = Hash::make($request->password);
            }

            $user->update([
                'name' => $request->name ,
                'email' => $request->email ,
                'phone' => $request->phone,
                'password' => $password,
                'image' => $imageName,
                'address' => $request->address,
                'date_of_birth' => $request->date_of_birth,
                'gender' => $request->gender,
            ]);

            $employee->update([
                'user_id' => $user->id ,
                'department_id' => $request->department_id ,
                'work_start_time' => $request->work_start_time,
                'work_end_time' => $request->work_end_time,
                'working_days' => $request->working_days,
                'hire_date' => $request->hire_date,
                'short_biography' => $request->short_biography,
                'status' => $request->status,
            ]);

            $doctor->update([
                'employee_id' => $employee->id ,
                'qualification' => $request->qualification,
                'experience_years' => $request->experience_years,
            ]);

            return response()->json(['data' => 1]);
        }
    }
    public function deleteDoctor($id){
        $doctor = Doctor::findOrFail($id);
        $employee = Employee::where('id', $doctor->employee_id)->first();
        $user = $employee ? User::where('id', $employee->user_id)->first() : null;

        if ($employee) {
            $employee->jobTitles()->detach();
        }

        $doctor->delete();
        $employee->delete();
        $user->delete();
        return response()->json(['success' => true]);
    }

    public function searchDoctorSchedules(){
        $departments = Department::all();
        $doctors = Doctor::all();
        return view('Backend.admin.doctors.schedules', compact('departments', 'doctors'));
    }

    public function searchDoctchedules(Request $request){
        $doctor_id = $request->doctor_id;
        $clinic_id = $request->clinic_id;
        $department_id = $request->department_id;
        $offset = (int) ($request->offset ?? 0);

        $departments = Department::all();
        $doctors = Doctor::all();

        $startOfWeek = Carbon::now()->startOfWeek(Carbon::SATURDAY)->addWeeks($offset);
        $endOfWeek = Carbon::now()->endOfWeek(Carbon::FRIDAY)->addWeeks($offset);

        $appointments = Appointment::where('doctor_id', $doctor_id)
            ->whereBetween('date', [$startOfWeek->toDateString(), $endOfWeek->toDateString()])
            ->get();

        return view('Backend.admin.doctors.schedules', [
            'appointments' => $appointments,
            'selectedDoctor' => Doctor::find($doctor_id),
            'departments' => $departments,
            'doctors' => $doctors,
            'clinic_id' => $clinic_id,
            'department_id' => $department_id,
            'doctor_id' => $doctor_id,
            'offset' => $offset,
            'startOfWeek' => $startOfWeek,
            'endOfWeek' => $endOfWeek,
        ]);
    }
use App\Models\Department;
use App\Models\Appointment;
use Illuminate\Http\Request;
use App\Models\EmployeeJobTitle;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;

class DoctorController extends Controller{


}
