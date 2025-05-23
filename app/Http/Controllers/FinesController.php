<?php

namespace App\Http\Controllers;

use App\Models\Fine;
use App\Models\FineSettings;
use App\Models\Student;
use App\Models\Event;
use App\Models\StudentAttendance;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FinesController extends Controller
{
    public function updateSettings(Request $request)
    {
        $request->validate([
            'fine_amount' => 'required|numeric|min:0',
            'morning_checkin' => 'nullable|boolean',
            'morning_checkout' => 'nullable|boolean',
            'afternoon_checkin' => 'nullable|boolean',
            'afternoon_checkout' => 'nullable|boolean'
        ]);

        $settings = FineSettings::firstOrCreate(['id' => 1], [
            'fine_amount' => 25.00
        ]);

        $settings->update([
            'fine_amount' => $request->fine_amount,
            'morning_checkin' => $request->has('morning_checkin'),
            'morning_checkout' => $request->has('morning_checkout'),
            'afternoon_checkin' => $request->has('afternoon_checkin'),
            'afternoon_checkout' => $request->has('afternoon_checkout')
        ]);

        return redirect()->route('logs')->with('success', 'Fine settings updated successfully');
    }

    public function calculateFines()
    {
        $event = Event::where('date', now()->format('Y-m-d'))->first();
        if (!$event) {
            return response()->json(['message' => 'No event today', 'success' => false]);
        }

        $settings = FineSettings::firstOrCreate(['id' => 1], [
            'fine_amount' => 25.00
        ]);

        $finesCreated = 0;

        // Get students who missed either check-in or check-out
        $students = Student::whereNotExists(function($query) use ($event) {
            $query->select(DB::raw(1))
                  ->from('student_attendances')
                  ->whereColumn('students.s_rfid', 'student_attendances.student_rfid')
                  ->where('event_id', $event->id)
                  ->whereNotNull('attend_checkIn')
                  ->whereNotNull('attend_checkOut');
        })->get();

        foreach ($students as $student) {
            Fine::updateOrCreate(
                [
                    'student_id' => $student->id,
                    'event_id' => $event->id
                ],
                [
                    'absences' => 1,
                    'fine_amount' => $settings->fine_amount,
                    'total_fines' => $settings->fine_amount
                ]
            );
            $finesCreated++;
        }

        return response()->json([
            'message' => "{$finesCreated} fine records created/updated",
            'success' => true
        ]);
    }

    protected function processMissingAttendance($event, $type, $settings, &$finesCreated)
    {
        $students = Student::whereNotIn('s_rfid', function($query) use ($event, $type) {
            $query->select('student_rfid')
                ->from('student_attendances')
                ->where('event_id', $event->id)
                ->whereNotNull("attend_{$type}");
        })->get();

        foreach ($students as $student) {
            Fine::updateOrCreate(
                [
                    'student_id' => $student->id,
                    'event_id' => $event->id
                ],
                [
                    'absences' => DB::raw('absences + 1'),
                    'fine_amount' => $settings->fine_amount,
                    'total_fines' => DB::raw('fine_amount * (absences + 1)'),
                    "{$type}_checkin" => false
                ]
            );
            $finesCreated++;
        }
    }


    // protected function calculateFine(){
    //      // CODE BELOW IS FOR FUTURE USE

    //      $settings = FineSettings::firstOrCreate(
    //         ['id' => 1],
    //         [
    //             'fine_amount' => 25.00,
    //             'morning_checkin' => true,
    //             'morning_checkout' => true,
    //             'afternoon_checkin' => true,
    //             'afternoon_checkout' => true
    //         ]
    //     );



    //     $fine = Fine::where('student_id', $student->id)
    //         ->where('event_id', $event->id)
    //         ->first();

    //     if ($fine) {
    //         // Morning check-in
    //         if ($time > $event->checkIn_start && $time < $event->checkIn_end) {
    //             $attendance->attend_checkIn = $currentTime;
    //             $attendance->morning_attendance = true;
    //             $attendance->save();

    //             if (!$fine->morning_checkin) {
    //                 $fine->morning_checkin = true;
    //                 $fine->absences -= 1;
    //                 $fine->total_fines = $fine->absences * $settings->fine_amount;
    //                 $fine->save();
    //             }
    //         }

    //         // Morning check-out
    //         if ($time > $event->checkOut_start && $time < $event->checkOut_end) {
    //             $attendance->attend_checkOut = $currentTime;
    //             $attendance->morning_attendance = true;
    //             $attendance->save();

    //             if (!$fine->morning_checkout) {
    //                 $fine->morning_checkout = true;
    //                 $fine->absences -= 1;
    //                 $fine->total_fines = $fine->absences * $settings->fine_amount;
    //                 $fine->save();
    //             }
    //         }

    //         // Afternoon check-in
    //         if ($time > $event->afternoon_checkIn_start && $time < $event->afternoon_checkIn_end) {
    //             $attendance->attend_afternoon_checkIn = $currentTime;
    //             $attendance->afternoon_attendance = true;
    //             $attendance->save();

    //             if (!$fine->afternoon_checkin) {
    //                 $fine->afternoon_checkin = true;
    //                 $fine->absences -= 1;
    //                 $fine->total_fines = $fine->absences * $settings->fine_amount;
    //                 $fine->save();
    //             }
    //         }

    //         // Afternoon check-out
    //         if ($time > $event->afternoon_checkOut_start && $time < $event->afternoon_checkOut_end) {
    //             $attendance->attend_afternoon_checkOut = $currentTime;
    //             $attendance->afternoon_attendance = true;
    //             $attendance->save();

    //             if (!$fine->afternoon_checkout) {
    //                 $fine->afternoon_checkout = true;
    //                 $fine->absences -= 1;
    //                 $fine->total_fines = $fine->absences * $settings->fine_amount;
    //                 $fine->save();
    //             }
    //         }


    //     }
    // }

}
