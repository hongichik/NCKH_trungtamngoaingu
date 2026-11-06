<?php

use App\Http\Controllers\Admin\BannerController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CertificateController;
use App\Http\Controllers\Admin\ContactController as AdminContactController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EditorUploadController;
use App\Http\Controllers\Admin\ExamController as AdminExamController;
use App\Http\Controllers\Admin\ExamRegistrationController as AdminExamRegistrationController;
use App\Http\Controllers\Admin\GalleryController as AdminGalleryController;
use App\Http\Controllers\Admin\MaintenanceController;
use App\Http\Controllers\Admin\SystemController;
use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\PostController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CertificateLookupController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\Portal\AuthController as PortalAuthController;
use App\Http\Controllers\Portal\ExamRegistrationController;
use App\Http\Controllers\Portal\ProfileController as PortalProfileController;
use App\Http\Controllers\Portal\ResultController as PortalResultController;
use App\Http\Controllers\PublicController;
use Illuminate\Support\Facades\Route;

// Public site
// Giọng đọc AI cho các nút "Nghe" / loa (public/js/speech.js) — có lưu đệm, chỉ người đã đăng nhập.
Route::get('/tts', \App\Http\Controllers\TtsController::class)->name('tts')->middleware('throttle:120,1');
Route::get('/', [PublicController::class, 'home'])->name('home');
Route::get('/tin-tuc', [PublicController::class, 'blogIndex'])->name('blog.index');
Route::get('/danh-muc/{category:slug}', [PublicController::class, 'category'])->name('category.show');
Route::get('/thu-vien-anh', [GalleryController::class, 'index'])->name('galleries.index');
Route::get('/thu-vien-anh/{gallery:slug}', [GalleryController::class, 'show'])->name('galleries.show');
Route::get('/tra-cuu-chung-chi/{serialNumber?}', [CertificateLookupController::class, 'show'])->name('certificates.lookup')->middleware('throttle:60,1');
Route::get('/lien-he', [ContactController::class, 'create'])->name('contact.create');
Route::post('/lien-he', [ContactController::class, 'store'])->name('contact.store')->middleware('throttle:5,10');

// Candidate portal: register/login, then complete profile before registering for an exam.
Route::get('/dang-ky-chung-chi', [PortalAuthController::class, 'show'])->name('portal.auth');
Route::post('/dang-ky-chung-chi/dang-ky', [PortalAuthController::class, 'register'])->name('portal.register');
Route::post('/dang-ky-chung-chi/dang-nhap', [PortalAuthController::class, 'login'])->name('portal.login');
Route::post('/thi-sinh/dang-xuat', [PortalAuthController::class, 'logout'])->name('portal.logout')->middleware('student.auth');

Route::prefix('thi-sinh')->name('portal.')->middleware('student.auth')->group(function () {
    Route::get('/', \App\Http\Controllers\Portal\HomeController::class)->name('home');
    Route::get('thong-tin', [PortalProfileController::class, 'edit'])->name('profile.edit');
    Route::get('doi-mat-khau', [PortalProfileController::class, 'password'])->name('password.edit');
    Route::post('doi-mat-khau', [PortalProfileController::class, 'updatePassword'])->name('password.update')->middleware('throttle:10,1');
    // POST, not PUT: this form has file inputs, and PHP silently drops
    // multipart fields (including _token) on a spoofed PUT request.
    Route::post('thong-tin', [PortalProfileController::class, 'update'])->name('profile.update');

    Route::get('dang-ky-thi/{type}', [ExamRegistrationController::class, 'show'])->whereIn('type', ['hsk', 'cntt'])->name('exam.show');
    Route::post('dang-ky-thi/{type}', [ExamRegistrationController::class, 'store'])->whereIn('type', ['hsk', 'cntt'])->name('exam.store');

    Route::get('ket-qua', [PortalResultController::class, 'index'])->name('results.index');

    // Học trực tuyến
    // Lớp học của tôi (học viên chỉ thấy bài được giao trong lớp mình)
    Route::get('lop-hoc', [\App\Http\Controllers\Portal\LearningController::class, 'classes'])->name('classes.index');
    Route::get('lop-hoc/{classroom}', [\App\Http\Controllers\Portal\LearningController::class, 'classroom'])->name('classes.show');
    Route::get('vao-lop/{token}', [\App\Http\Controllers\Portal\LearningController::class, 'invite'])->name('classes.invite');
    Route::get('lop-hoc/{classroom}/bai-hoc/{lesson}', [\App\Http\Controllers\Portal\LessonController::class, 'show'])->name('lessons.show');
    Route::get('video/{video}/nguon', [\App\Http\Controllers\Portal\LessonController::class, 'source'])->name('video.source')->middleware('throttle:60,1');
    Route::get('video/{video}/phat', [\App\Http\Controllers\Portal\LessonController::class, 'stream'])->name('video.stream')->middleware('throttle:900,1');
    Route::get('video/{video}/hls', [\App\Http\Controllers\Portal\LessonController::class, 'hls'])->name('video.hls')->middleware('throttle:1500,1');
    Route::get('video/{video}/phu-de', [\App\Http\Controllers\Portal\LessonController::class, 'captions'])->name('video.captions');
    Route::post('video/{video}/tien-do', [\App\Http\Controllers\Portal\LessonController::class, 'progress'])->name('video.progress')->middleware('throttle:30,1');
    Route::get('tai-lieu/{material}', [\App\Http\Controllers\Portal\LessonController::class, 'material'])->name('lessons.material');
    Route::post('bai-tuy-chinh/{cce}/lam-bai', [\App\Http\Controllers\Portal\CustomExerciseController::class, 'start'])->name('custom.start');
    Route::post('lop-hoc/{classroom}/bai-tap/{exercise}/lam-bai', [\App\Http\Controllers\Portal\CustomExerciseController::class, 'startExercise'])->name('custom.start-exercise');
    Route::get('bai-lam-tuy-chinh/{attempt}', [\App\Http\Controllers\Portal\CustomExerciseController::class, 'show'])->name('custom.do');
    Route::post('bai-lam-tuy-chinh/{attempt}/luu', [\App\Http\Controllers\Portal\CustomExerciseController::class, 'save'])->name('custom.save')->middleware('throttle:60,1');
    Route::post('bai-lam-tuy-chinh/{attempt}/nop', [\App\Http\Controllers\Portal\CustomExerciseController::class, 'submit'])->name('custom.submit')->middleware('throttle:20,1');
    Route::post('bai-lam-tuy-chinh/{attempt}/ghi-am/{item}', [\App\Http\Controllers\Portal\CustomExerciseController::class, 'record'])->name('custom.record')->middleware('throttle:60,1');
    Route::get('bai-lam-tuy-chinh/{attempt}/ket-qua', [\App\Http\Controllers\Portal\CustomExerciseController::class, 'result'])->name('custom.result');
    Route::get('bai-lam-tuy-chinh/{attempt}/trang-thai', [\App\Http\Controllers\Portal\CustomExerciseController::class, 'status'])->name('custom.status');
    Route::get('bai-lam-tuy-chinh/{attempt}/nghe/{item}', [\App\Http\Controllers\Portal\CustomExerciseController::class, 'audio'])->name('custom.audio');
    Route::get('bai-lam-tuy-chinh/{attempt}/anh/{item}', [\App\Http\Controllers\Portal\CustomExerciseController::class, 'image'])->name('custom.image');
    Route::post('vao-lop/{token}', [\App\Http\Controllers\Portal\LearningController::class, 'acceptInvite'])->name('classes.accept')->middleware('throttle:20,1');
    Route::get('tien-do', [\App\Http\Controllers\Portal\ProgressController::class, 'index'])->name('progress.index');
    Route::get('tien-do/bao-cao', [\App\Http\Controllers\Portal\ProgressController::class, 'report'])->name('progress.report');
    Route::post('tien-do/muc-tieu', [\App\Http\Controllers\Portal\ProgressController::class, 'goal'])->name('progress.goal');
    Route::get('so-loi-sai', [\App\Http\Controllers\Portal\ProgressController::class, 'mistakes'])->name('mistakes.index');
    Route::post('so-loi-sai/lam-lai', [\App\Http\Controllers\Portal\ProgressController::class, 'retry'])->name('mistakes.retry')->middleware('throttle:30,1');
    Route::post('so-loi-sai/bai-luyen', [\App\Http\Controllers\Portal\ProgressController::class, 'createPractice'])->name('practice.create')->middleware('throttle:10,1');
    Route::get('bai-luyen/{practice}', [\App\Http\Controllers\Portal\ProgressController::class, 'showPractice'])->name('practice.show');
    Route::post('bai-luyen/{practice}', [\App\Http\Controllers\Portal\ProgressController::class, 'submitPractice'])->name('practice.submit')->middleware('throttle:20,1');
    Route::get('on-tu-vung', [\App\Http\Controllers\Portal\VocabController::class, 'index'])->name('vocab.index');
    Route::get('on-tu-vung/hoc', [\App\Http\Controllers\Portal\VocabController::class, 'study'])->name('vocab.study');
    Route::post('on-tu-vung/danh-gia', [\App\Http\Controllers\Portal\VocabController::class, 'review'])->name('vocab.review')->middleware('throttle:240,1');
    Route::get('on-tu-vung/luyen-tieng-trung/{unit}', [\App\Http\Controllers\Portal\VocabController::class, 'drill'])->where('unit', '[ul]?[0-9]+')->name('vocab.drill');
    Route::post('on-tu-vung/ket-qua-luyen', [\App\Http\Controllers\Portal\VocabController::class, 'progress'])->name('vocab.progress')->middleware('throttle:60,1');
    Route::get('on-tu-vung/tap-viet/{unit}', [\App\Http\Controllers\Portal\VocabController::class, 'writing'])->where('unit', '[ul]?[0-9]+')->name('vocab.writing');
    Route::redirect('hoc-tap', '/thi-sinh/lop-hoc');
    Route::redirect('bai-duoc-giao', '/thi-sinh/lop-hoc');
    Route::post('bai-duoc-giao/{assignment}/lam-bai', [\App\Http\Controllers\Portal\LearningController::class, 'startAssignment'])->name('assignments.start');
    Route::get('hoc-tap/{course}/unit/{unit}/xem/{kind}', [\App\Http\Controllers\Portal\LearningController::class, 'material'])->name('learning.material');
    Route::get('hoc-tap/{course}/lam-bai/{test}', [\App\Http\Controllers\Portal\LearningController::class, 'test'])->name('learning.test');
    Route::post('hoc-tap/{course}/lam-bai/{test}/nop', [\App\Http\Controllers\Portal\LearningController::class, 'submitTest'])->name('learning.test-submit')->middleware('throttle:20,1');
    Route::get('hoc-tap/{course}/lam-bai/{test}/ket-qua', [\App\Http\Controllers\Portal\LearningController::class, 'testResult'])->name('learning.test-result');
    Route::get('hoc-tap/{course}/lam-bai/{test}/trang-thai', [\App\Http\Controllers\Portal\LearningController::class, 'testStatus'])->name('learning.test-status');
    Route::get('hoc-tap/{course}/bai-tap/{set}', [\App\Http\Controllers\Portal\LearningController::class, 'exercise'])->name('learning.exercise');
    Route::post('hoc-tap/{course}/bai-tap/{set}', [\App\Http\Controllers\Portal\LearningController::class, 'submit'])->name('learning.submit')->middleware('throttle:30,1');
    Route::get('hoc-tap/{course}/bai-tap/{set}/audio', [\App\Http\Controllers\Portal\LearningController::class, 'audio'])->name('learning.audio');
    Route::get('hoc-tap/{course}/ket-qua/{attempt}', [\App\Http\Controllers\Portal\LearningController::class, 'result'])->name('learning.result');
    Route::get('hoc-tap/{course}/ket-qua/{attempt}/trang-thai', [\App\Http\Controllers\Portal\LearningController::class, 'resultStatus'])->name('learning.result-status');
    Route::post('hoc-tap/{course}/bai-tap/{set}/ghi-am/{code}', [\App\Http\Controllers\Portal\LearningController::class, 'record'])->name('learning.record')->middleware('throttle:60,1');
});

// Admin auth
Route::get('/admin/login', [LoginController::class, 'showLoginForm'])->name('login')->middleware('guest');
Route::post('/admin/login', [LoginController::class, 'login'])->middleware('guest');
Route::post('/admin/logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');

// Admin panel
Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::middleware('permission:posts,view')->group(function () {
        Route::get('posts', [PostController::class, 'index'])->name('posts.index');
    });
    Route::middleware('permission:posts,create')->group(function () {
        Route::get('posts/create', [PostController::class, 'create'])->name('posts.create');
        Route::post('posts', [PostController::class, 'store'])->name('posts.store');
    });
    // Image uploads from the rich-text editor (posts, pages); permission checked in the controller.
    Route::post('editor/upload-image', [EditorUploadController::class, 'store'])->name('editor.upload');
    Route::middleware('permission:posts,edit')->group(function () {
        Route::get('posts/{post}/edit', [PostController::class, 'edit'])->name('posts.edit');
        // POST, not PUT: PHP does not parse multipart/form-data bodies on a
        // method-spoofed PUT request, silently dropping form fields (including
        // _token) whenever this form's image upload is submitted.
        Route::post('posts/{post}', [PostController::class, 'update'])->name('posts.update');
    });
    Route::delete('posts/{post}', [PostController::class, 'destroy'])->name('posts.destroy')->middleware('permission:posts,delete');
    Route::delete('posts/{post}/images/{image}', [PostController::class, 'destroyImage'])->name('posts.images.destroy')->middleware('permission:posts,delete');

    Route::middleware('permission:categories,view')->group(function () {
        Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
    });
    Route::middleware('permission:categories,create')->group(function () {
        Route::get('categories/create', [CategoryController::class, 'create'])->name('categories.create');
        Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
    });
    Route::middleware('permission:categories,edit')->group(function () {
        Route::get('categories/{category}/edit', [CategoryController::class, 'edit'])->name('categories.edit');
        Route::put('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
    });
    Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy')->middleware('permission:categories,delete');

    Route::middleware('permission:students,view')->group(function () {
        Route::get('students', [StudentController::class, 'index'])->name('students.index');
    });
    Route::middleware('permission:students,create')->group(function () {
        Route::get('students/create', [StudentController::class, 'create'])->name('students.create');
        Route::post('students', [StudentController::class, 'store'])->name('students.store');
    });
    Route::middleware('permission:students,edit')->group(function () {
        Route::get('students/{student}/edit', [StudentController::class, 'edit'])->name('students.edit');
        Route::put('students/{student}', [StudentController::class, 'update'])->name('students.update');
    });
    Route::delete('students/{student}', [StudentController::class, 'destroy'])->name('students.destroy')->middleware('permission:students,delete');

    Route::middleware('permission:certificates,view')->group(function () {
        Route::get('certificates', [CertificateController::class, 'index'])->name('certificates.index');
    });
    Route::middleware('permission:certificates,create')->group(function () {
        Route::get('certificates/create', [CertificateController::class, 'create'])->name('certificates.create');
        Route::post('certificates', [CertificateController::class, 'store'])->name('certificates.store');
    });
    Route::middleware('permission:certificates,edit')->group(function () {
        Route::get('certificates/{certificate}/edit', [CertificateController::class, 'edit'])->name('certificates.edit');
        Route::put('certificates/{certificate}', [CertificateController::class, 'update'])->name('certificates.update');
    });
    Route::delete('certificates/{certificate}', [CertificateController::class, 'destroy'])->name('certificates.destroy')->middleware('permission:certificates,delete');

    Route::middleware('permission:galleries,view')->group(function () {
        Route::get('galleries', [AdminGalleryController::class, 'index'])->name('galleries.index');
    });
    Route::middleware('permission:galleries,create')->group(function () {
        Route::get('galleries/create', [AdminGalleryController::class, 'create'])->name('galleries.create');
        Route::post('galleries', [AdminGalleryController::class, 'store'])->name('galleries.store');
    });
    Route::middleware('permission:galleries,edit')->group(function () {
        Route::get('galleries/{gallery}/edit', [AdminGalleryController::class, 'edit'])->name('galleries.edit');
        // POST, not PUT: see note on posts.update above (multipart + spoofed PUT).
        Route::post('galleries/{gallery}', [AdminGalleryController::class, 'update'])->name('galleries.update');
    });
    Route::middleware('permission:galleries,delete')->group(function () {
        Route::delete('galleries/{gallery}', [AdminGalleryController::class, 'destroy'])->name('galleries.destroy');
        Route::delete('galleries/{gallery}/images/{image}', [AdminGalleryController::class, 'destroyImage'])->name('galleries.images.destroy');
    });

    Route::middleware('permission:pages,view')->group(function () {
        Route::get('pages', [PageController::class, 'index'])->name('pages.index');
    });
    Route::middleware('permission:pages,create')->group(function () {
        Route::get('pages/create', [PageController::class, 'create'])->name('pages.create');
        Route::post('pages', [PageController::class, 'store'])->name('pages.store');
    });
    Route::middleware('permission:pages,edit')->group(function () {
        Route::get('pages/{page}/edit', [PageController::class, 'edit'])->name('pages.edit');
        // POST, not PUT: see note on posts.update above (multipart + spoofed PUT).
        Route::post('pages/{page}', [PageController::class, 'update'])->name('pages.update');
    });
    Route::delete('pages/{page}', [PageController::class, 'destroy'])->name('pages.destroy')->middleware('permission:pages,delete');

    // Public-site menus (header + footer columns)
    Route::middleware('permission:menus,view')->group(function () {
        Route::get('menus', [MenuController::class, 'index'])->name('menus.index');
    });
    Route::middleware('permission:menus,create')->group(function () {
        Route::get('menus/create', [MenuController::class, 'create'])->name('menus.create');
        Route::post('menus', [MenuController::class, 'store'])->name('menus.store');
    });
    Route::middleware('permission:menus,edit')->group(function () {
        Route::post('menus/reorder', [MenuController::class, 'reorder'])->name('menus.reorder');
        Route::get('menus/{menu}/edit', [MenuController::class, 'edit'])->name('menus.edit');
        Route::put('menus/{menu}', [MenuController::class, 'update'])->name('menus.update');
        Route::post('menus/{menu}/toggle', [MenuController::class, 'toggle'])->name('menus.toggle');
    });
    Route::delete('menus/{menu}', [MenuController::class, 'destroy'])->name('menus.destroy')->middleware('permission:menus,delete');

    // Homepage banners
    Route::middleware('permission:banners,view')->group(function () {
        Route::get('banners', [BannerController::class, 'index'])->name('banners.index');
    });
    Route::middleware('permission:banners,create')->group(function () {
        Route::get('banners/create', [BannerController::class, 'create'])->name('banners.create');
        Route::post('banners', [BannerController::class, 'store'])->name('banners.store');
    });
    Route::middleware('permission:banners,edit')->group(function () {
        Route::post('banners/reorder', [BannerController::class, 'reorder'])->name('banners.reorder');
        Route::get('banners/{banner}/edit', [BannerController::class, 'edit'])->name('banners.edit');
        // POST, not PUT: see note on posts.update above (multipart + spoofed PUT).
        Route::post('banners/{banner}', [BannerController::class, 'update'])->name('banners.update');
        Route::post('banners/{banner}/toggle', [BannerController::class, 'toggle'])->name('banners.toggle');
    });
    Route::delete('banners/{banner}', [BannerController::class, 'destroy'])->name('banners.destroy')->middleware('permission:banners,delete');

    // Site-wide settings (name, logo, contact info, homepage texts)
    Route::get('settings', [SettingController::class, 'edit'])->name('settings.edit')->middleware('permission:settings,view');
    Route::post('settings', [SettingController::class, 'update'])->name('settings.update')->middleware('permission:settings,edit');

    Route::middleware('permission:contacts,view')->group(function () {
        Route::get('contacts', [AdminContactController::class, 'index'])->name('contacts.index');
        Route::get('contacts/{contact}', [AdminContactController::class, 'show'])->name('contacts.show');
    });
    Route::post('contacts/{contact}/reply', [AdminContactController::class, 'reply'])->name('contacts.reply')->middleware('permission:contacts,reply');
    Route::delete('contacts/{contact}', [AdminContactController::class, 'destroy'])->name('contacts.destroy')->middleware('permission:contacts,delete');

    // Exam sessions, levels & pricing (HSK / CNTT)
    Route::middleware('permission:exams,view')->group(function () {
        Route::get('exams', [AdminExamController::class, 'index'])->name('exams.index');
    });
    Route::middleware('permission:exams,create')->group(function () {
        Route::post('exams/levels', [AdminExamController::class, 'storeLevel'])->name('exams.levels.store');
        Route::get('exams/sessions/create', [AdminExamController::class, 'createSession'])->name('exams.sessions.create');
        Route::post('exams/sessions', [AdminExamController::class, 'storeSession'])->name('exams.sessions.store');
    });
    Route::middleware('permission:exams,edit')->group(function () {
        Route::put('exams/levels/{level}', [AdminExamController::class, 'updateLevel'])->name('exams.levels.update');
        Route::get('exams/sessions/{session}/edit', [AdminExamController::class, 'editSession'])->name('exams.sessions.edit');
        Route::put('exams/sessions/{session}', [AdminExamController::class, 'updateSession'])->name('exams.sessions.update');
    });
    Route::middleware('permission:exams,delete')->group(function () {
        Route::delete('exams/levels/{level}', [AdminExamController::class, 'destroyLevel'])->name('exams.levels.destroy');
        Route::delete('exams/sessions/{session}', [AdminExamController::class, 'destroySession'])->name('exams.sessions.destroy');
    });

    // Candidate exam registrations (view + confirm payment)
    Route::middleware('permission:exam_registrations,view')->group(function () {
        Route::get('exam-registrations', [AdminExamRegistrationController::class, 'index'])->name('exam-registrations.index');
    });
    Route::middleware('permission:exam_registrations,edit')->group(function () {
        Route::post('exam-registrations/{registration}/confirm-payment', [AdminExamRegistrationController::class, 'confirmPayment'])->name('exam-registrations.confirm-payment');
        Route::post('exam-registrations/{registration}/cancel', [AdminExamRegistrationController::class, 'cancel'])->name('exam-registrations.cancel');
    });
    Route::delete('exam-registrations/{registration}', [AdminExamRegistrationController::class, 'destroy'])->name('exam-registrations.destroy')->middleware('permission:exam_registrations,delete');

    // Tài khoản giáo viên (giáo viên dùng cổng riêng /giao-vien)
    Route::middleware('permission:teachers,view')->get('teachers', [\App\Http\Controllers\Admin\TeacherController::class, 'index'])->name('teachers.index');
    Route::middleware('permission:teachers,create')->group(function () {
        Route::get('teachers/create', [\App\Http\Controllers\Admin\TeacherController::class, 'create'])->name('teachers.create');
        Route::post('teachers', [\App\Http\Controllers\Admin\TeacherController::class, 'store'])->name('teachers.store');
    });
    Route::middleware('permission:teachers,edit')->group(function () {
        Route::get('teachers/{teacher}/edit', [\App\Http\Controllers\Admin\TeacherController::class, 'edit'])->name('teachers.edit');
        Route::put('teachers/{teacher}', [\App\Http\Controllers\Admin\TeacherController::class, 'update'])->name('teachers.update');
    });
    Route::delete('teachers/{teacher}', [\App\Http\Controllers\Admin\TeacherController::class, 'destroy'])->name('teachers.destroy')->middleware('permission:teachers,delete');

    // Tài khoản học viên (thí sinh) của cổng học trực tuyến.
    Route::middleware('permission:learning,view')->group(function () {
        Route::get('student-accounts', [\App\Http\Controllers\Admin\StudentAccountController::class, 'index'])->name('student-accounts.index');
        Route::get('student-accounts/{student}', [\App\Http\Controllers\Admin\StudentAccountController::class, 'show'])->name('student-accounts.show')->whereNumber('student');
    });
    Route::middleware('permission:learning,create')->group(function () {
        Route::post('student-accounts', [\App\Http\Controllers\Admin\StudentAccountController::class, 'store'])->name('student-accounts.store');
        Route::post('student-accounts/import', [\App\Http\Controllers\Admin\StudentAccountController::class, 'import'])->name('student-accounts.import');
        Route::post('student-accounts/import/save', [\App\Http\Controllers\Admin\StudentAccountController::class, 'importSave'])->name('student-accounts.import.commit');
        Route::get('student-accounts/import/accounts.csv', [\App\Http\Controllers\Admin\StudentAccountController::class, 'credentials'])->name('student-accounts.import.credentials');
    });
    Route::middleware('permission:learning,edit')->group(function () {
        Route::put('student-accounts/{student}', [\App\Http\Controllers\Admin\StudentAccountController::class, 'update'])->name('student-accounts.update');
        Route::post('student-accounts/{student}/reset-password', [\App\Http\Controllers\Admin\StudentAccountController::class, 'resetPassword'])->name('student-accounts.reset');
        Route::post('student-accounts/{student}/lock', [\App\Http\Controllers\Admin\StudentAccountController::class, 'toggleLock'])->name('student-accounts.lock');
        Route::post('student-accounts/{student}/classes', [\App\Http\Controllers\Admin\StudentAccountController::class, 'addToClass'])->name('student-accounts.classes.add');
        Route::delete('student-accounts/{student}/classes/{classroom}', [\App\Http\Controllers\Admin\StudentAccountController::class, 'removeFromClass'])->name('student-accounts.classes.remove');
    });

    // Khoá học & lớp học (V2 bước 2–3).
    Route::middleware('permission:learning,view')->group(function () {
        Route::get('courses', [\App\Http\Controllers\Admin\CourseController::class, 'index'])->name('courses.index');
        Route::get('classrooms', [\App\Http\Controllers\Admin\ClassroomController::class, 'index'])->name('classrooms.index');
        Route::get('classrooms/{classroom}', [\App\Http\Controllers\Admin\ClassroomController::class, 'show'])->name('classrooms.show')->whereNumber('classroom');
        Route::get('students-import-template', [\App\Http\Controllers\Admin\ClassroomController::class, 'template'])->name('students.import-template');
    });
    Route::middleware('permission:learning,create')->group(function () {
        Route::get('courses/create', [\App\Http\Controllers\Admin\CourseController::class, 'create'])->name('courses.create');
        Route::post('courses', [\App\Http\Controllers\Admin\CourseController::class, 'store'])->name('courses.store');
        Route::get('classrooms/create', [\App\Http\Controllers\Admin\ClassroomController::class, 'create'])->name('classrooms.create');
        Route::post('classrooms', [\App\Http\Controllers\Admin\ClassroomController::class, 'store'])->name('classrooms.store');
    });
    Route::middleware('permission:learning,edit')->group(function () {
        Route::get('courses/{course}/edit', [\App\Http\Controllers\Admin\CourseController::class, 'edit'])->name('courses.edit');
        Route::put('courses/{course}', [\App\Http\Controllers\Admin\CourseController::class, 'update'])->name('courses.update');
        Route::get('classrooms/{classroom}/edit', [\App\Http\Controllers\Admin\ClassroomController::class, 'edit'])->name('classrooms.edit');
        Route::put('classrooms/{classroom}', [\App\Http\Controllers\Admin\ClassroomController::class, 'update'])->name('classrooms.update');
        Route::post('classrooms/{classroom}/import', [\App\Http\Controllers\Admin\ClassroomController::class, 'import'])->name('classrooms.import');
        Route::post('classrooms/{classroom}/import/save', [\App\Http\Controllers\Admin\ClassroomController::class, 'importSave'])->name('classrooms.import.commit');
        Route::get('classrooms/{classroom}/import/accounts.csv', [\App\Http\Controllers\Admin\ClassroomController::class, 'credentials'])->name('classrooms.import.credentials');
        Route::get('classrooms/{classroom}/students/search', [\App\Http\Controllers\Admin\ClassroomController::class, 'searchStudents'])->name('classrooms.students.search');
        Route::post('classrooms/{classroom}/students/add-existing', [\App\Http\Controllers\Admin\ClassroomController::class, 'addExisting'])->name('classrooms.students.add-existing');
        Route::put('classrooms/{classroom}/lesson-schedule', [\App\Http\Controllers\Admin\ClassroomController::class, 'lessonSchedule'])->name('classrooms.lesson-schedule');
        Route::post('classrooms/{classroom}/students/{student}/reset-password', [\App\Http\Controllers\Admin\ClassroomController::class, 'resetPassword'])->name('classrooms.students.reset');
        Route::delete('classrooms/{classroom}/students/{student}', [\App\Http\Controllers\Admin\ClassroomController::class, 'removeStudent'])->name('classrooms.students.remove');
    });
    Route::middleware('permission:learning,edit')->prefix('courses/{course}')->name('courses.lessons.')->group(function () {
        Route::get('lessons', [\App\Http\Controllers\Admin\LessonController::class, 'index'])->name('index');
        Route::post('lessons', [\App\Http\Controllers\Admin\LessonController::class, 'store'])->name('store');
        Route::post('lessons/sap-xep', [\App\Http\Controllers\Admin\LessonController::class, 'reorder'])->name('reorder');
        Route::get('lessons/{lesson}', [\App\Http\Controllers\Admin\LessonController::class, 'edit'])->name('edit');
        Route::put('lessons/{lesson}', [\App\Http\Controllers\Admin\LessonController::class, 'update'])->name('update');
        Route::delete('lessons/{lesson}', [\App\Http\Controllers\Admin\LessonController::class, 'destroy'])->name('destroy');
        Route::post('lessons/{lesson}/ai-tao-bai-tap', [\App\Http\Controllers\Admin\LessonController::class, 'aiCompose'])->name('ai-compose');
        Route::post('lessons/tu-vung-ai', [\App\Http\Controllers\Admin\LessonController::class, 'aiVocabularyAll'])->name('vocabulary.ai-all');
        Route::put('lessons/{lesson}/tu-vung', [\App\Http\Controllers\Admin\LessonController::class, 'saveVocabulary'])->name('vocabulary');
        Route::post('lessons/{lesson}/tu-vung/ai', [\App\Http\Controllers\Admin\LessonController::class, 'aiVocabulary'])->name('vocabulary.ai');
        Route::post('lessons/{lesson}/video', [\App\Http\Controllers\Admin\LessonController::class, 'uploadVideo'])->name('video.upload');
        Route::post('lessons/{lesson}/video-bunny', [\App\Http\Controllers\Admin\LessonController::class, 'addBunny'])->name('video.bunny');
        Route::put('lessons/{lesson}/video/{video}', [\App\Http\Controllers\Admin\LessonController::class, 'updateVideo'])->name('video.update');
        Route::delete('lessons/{lesson}/video/{video}', [\App\Http\Controllers\Admin\LessonController::class, 'destroyVideo'])->name('video.destroy');
        Route::get('lessons/{lesson}/video/{video}/xem', [\App\Http\Controllers\Admin\LessonController::class, 'previewVideo'])->name('video.preview');
        Route::post('lessons/{lesson}/tai-lieu', [\App\Http\Controllers\Admin\LessonController::class, 'uploadMaterial'])->name('material.upload');
        Route::delete('lessons/{lesson}/tai-lieu/{material}', [\App\Http\Controllers\Admin\LessonController::class, 'destroyMaterial'])->name('material.destroy');
    });
    Route::middleware('permission:learning,edit')->prefix('courses/{course}')->name('courses.ai-import.')->group(function () {
        Route::get('ai-import', [\App\Http\Controllers\Admin\CustomExerciseController::class, 'aiCreate'])->name('create');
        Route::post('ai-import', [\App\Http\Controllers\Admin\CustomExerciseController::class, 'aiStore'])->name('store');
        Route::get('ai-import/file-mau.docx', [\App\Http\Controllers\Admin\CustomExerciseController::class, 'aiTemplate'])->name('template');
        Route::get('ai-import/{import}', [\App\Http\Controllers\Admin\CustomExerciseController::class, 'aiShow'])->name('show');
        Route::delete('ai-import/{import}', [\App\Http\Controllers\Admin\CustomExerciseController::class, 'aiDestroy'])->name('destroy');
        Route::post('ai-import/{import}/tai-lieu', [\App\Http\Controllers\Admin\CustomExerciseController::class, 'aiDocument'])->name('document');
        Route::post('ai-import/{import}/file-nghe', [\App\Http\Controllers\Admin\CustomExerciseController::class, 'aiAudio'])->name('audio');
        Route::get('ai-import/{import}/file-nghe/{audioId}', [\App\Http\Controllers\Admin\CustomExerciseController::class, 'aiAudioFile'])->name('audio-file');
        Route::post('ai-import/{import}/bat-dau', [\App\Http\Controllers\Admin\CustomExerciseController::class, 'aiBegin'])->name('begin');
        Route::post('ai-import/{import}/tick', [\App\Http\Controllers\Admin\CustomExerciseController::class, 'aiTick'])->name('tick')->middleware('throttle:40,1');
        Route::put('ai-import/{import}/phan/{section}', [\App\Http\Controllers\Admin\CustomExerciseController::class, 'aiUpdateSection'])->name('section.update')->whereNumber('section');
        Route::post('ai-import/{import}/phan/{section}/file-nghe', [\App\Http\Controllers\Admin\CustomExerciseController::class, 'aiSectionAudio'])->name('section.audio')->whereNumber('section');
        Route::post('ai-import/{import}/phan/{section}/giong-ai', [\App\Http\Controllers\Admin\CustomExerciseController::class, 'aiSectionTts'])->name('section.tts')->whereNumber('section');
        Route::post('ai-import/{import}/phan/{section}/anh', [\App\Http\Controllers\Admin\CustomExerciseController::class, 'aiSectionImage'])->name('section.image')->whereNumber('section');
        Route::get('ai-import/{import}/phan/{section}/anh', [\App\Http\Controllers\Admin\CustomExerciseController::class, 'aiSectionImageFile'])->name('section.image-file')->whereNumber('section');
        Route::post('ai-import/{import}/phan/{section}/dung-lai', [\App\Http\Controllers\Admin\CustomExerciseController::class, 'aiRebuild'])->name('section.rebuild')->whereNumber('section');
        Route::post('ai-import/{import}/dung-lai-tat-ca', [\App\Http\Controllers\Admin\CustomExerciseController::class, 'aiRebuildAll'])->name('rebuild-all');
        Route::post('ai-import/{import}/phan/{section}/di-chuyen', [\App\Http\Controllers\Admin\CustomExerciseController::class, 'aiMoveSection'])->name('section.move')->whereNumber('section');
        Route::delete('ai-import/{import}/phan/{section}', [\App\Http\Controllers\Admin\CustomExerciseController::class, 'aiDeleteSection'])->name('section.destroy')->whereNumber('section');
        Route::post('ai-import/{import}/file-nghe/{audioId}/chep-loi', [\App\Http\Controllers\Admin\CustomExerciseController::class, 'aiTranscribe'])->name('audio.transcribe');
        Route::post('ai-import/{import}/luu', [\App\Http\Controllers\Admin\CustomExerciseController::class, 'aiSave'])->name('save');
    });
    Route::middleware('permission:learning,edit')->prefix('courses/{course}')->name('courses.custom.')->group(function () {
        Route::get('custom-exercises', [\App\Http\Controllers\Admin\CustomExerciseController::class, 'index'])->name('index');
        Route::post('custom-exercises', [\App\Http\Controllers\Admin\CustomExerciseController::class, 'store'])->name('store');
        Route::get('custom-exercises/{exercise}', [\App\Http\Controllers\Admin\CustomExerciseController::class, 'edit'])->name('edit');
        Route::put('custom-exercises/{exercise}', [\App\Http\Controllers\Admin\CustomExerciseController::class, 'update'])->name('update');
        Route::delete('custom-exercises/{exercise}', [\App\Http\Controllers\Admin\CustomExerciseController::class, 'destroy'])->name('destroy');
        Route::patch('custom-exercises/{exercise}/trang-thai', [\App\Http\Controllers\Admin\CustomExerciseController::class, 'quickStatus'])->name('status');
        Route::post('custom-exercises/{exercise}/nhan-ban', [\App\Http\Controllers\Admin\CustomExerciseController::class, 'duplicate'])->name('duplicate');
        Route::post('custom-exercises/{exercise}/cham-lai', [\App\Http\Controllers\Admin\CustomExerciseController::class, 'regrade'])->name('regrade');
        Route::get('custom-exercises/{exercise}/xem-truoc', [\App\Http\Controllers\Admin\CustomExerciseController::class, 'preview'])->name('preview');
        Route::post('custom-exercises/{exercise}/phan', [\App\Http\Controllers\Admin\CustomExerciseController::class, 'itemStore'])->name('items.store');
        Route::put('custom-exercises/{exercise}/phan/{item}', [\App\Http\Controllers\Admin\CustomExerciseController::class, 'itemUpdate'])->name('items.update');
        Route::delete('custom-exercises/{exercise}/phan/{item}', [\App\Http\Controllers\Admin\CustomExerciseController::class, 'itemDestroy'])->name('items.destroy');
        Route::post('custom-exercises/{exercise}/phan/{item}/di-chuyen', [\App\Http\Controllers\Admin\CustomExerciseController::class, 'itemMove'])->name('items.move');
        Route::post('custom-exercises/{exercise}/phan/{item}/ai', [\App\Http\Controllers\Admin\CustomExerciseController::class, 'itemAi'])->name('items.ai');
        Route::post('custom-exercises/{exercise}/ai-trang-thai', [\App\Http\Controllers\Admin\CustomExerciseController::class, 'itemAiStatus'])->name('items.ai-status')->middleware('throttle:60,1');
        Route::post('custom-exercises/{exercise}/phan/{item}/file-nghe', [\App\Http\Controllers\Admin\CustomExerciseController::class, 'itemAudio'])->name('items.audio');
        Route::get('custom-exercises/{exercise}/phan/{item}/file-nghe', [\App\Http\Controllers\Admin\CustomExerciseController::class, 'itemAudioFile'])->name('items.audio-file');
        Route::post('custom-exercises/{exercise}/phan/{item}/anh', [\App\Http\Controllers\Admin\CustomExerciseController::class, 'itemImage'])->name('items.image');
        Route::get('custom-exercises/{exercise}/phan/{item}/anh', [\App\Http\Controllers\Admin\CustomExerciseController::class, 'itemImageFile'])->name('items.image-file');
    });
    Route::middleware('permission:learning,edit')->name('classrooms.')->group(function () {
        Route::get('classrooms/{classroom}/bai-tap/{exercise}/ket-qua', [\App\Http\Controllers\Admin\ClassroomController::class, 'customOpen'])->name('custom.open');
        Route::get('classrooms/{classroom}/bai-tuy-chinh/{cce}', [\App\Http\Controllers\Admin\ClassroomController::class, 'customResults'])->name('custom.results');
        Route::put('classrooms/{classroom}/bai-tuy-chinh/{cce}', [\App\Http\Controllers\Admin\ClassroomController::class, 'customUpdateAssignment'])->name('custom.update');
        Route::delete('classrooms/{classroom}/bai-tuy-chinh/{cce}', [\App\Http\Controllers\Admin\ClassroomController::class, 'customUnassign'])->name('custom.unassign');
        Route::get('classrooms/{classroom}/bai-tuy-chinh/{cce}/bai-lam/{attempt}', [\App\Http\Controllers\Admin\ClassroomController::class, 'customReview'])->name('custom.review');
        Route::put('classrooms/{classroom}/bai-tuy-chinh/{cce}/bai-lam/{attempt}', [\App\Http\Controllers\Admin\ClassroomController::class, 'customSaveReview'])->name('custom.review.save');
        Route::get('classrooms/{classroom}/bai-tuy-chinh/{cce}/bai-lam/{attempt}/ghi-am/{item}', [\App\Http\Controllers\Admin\ClassroomController::class, 'customRecording'])->name('custom.recording');
    });
    Route::middleware('permission:learning,delete')->group(function () {
        Route::delete('courses/{course}', [\App\Http\Controllers\Admin\CourseController::class, 'destroy'])->name('courses.destroy');
        Route::delete('classrooms/{classroom}', [\App\Http\Controllers\Admin\ClassroomController::class, 'destroy'])->name('classrooms.destroy');
    });

    // User & permission management -- restricted to the highest-level (super) admin only,
    // so a limited account can never grant itself (or anyone else) more access.
    Route::middleware('super-admin')->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::get('users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

        // Runs `php artisan storage:link` for hosting without SSH access.
        Route::get('storage-link', [MaintenanceController::class, 'storageLink'])->name('storage-link');
    });

    // Dự phòng khi chưa cài cron: trang tiến độ gọi liên tục để xử lý từng việc nền AI (< 25 giây/lần).
    Route::post('ai-jobs/tick', function (\App\Support\Ai\Jobs\AiJobRunner $runner) {
        $stats = $runner->work(20, 1);

        return response()->json(['ok' => true, 'processed' => $stats, 'counts' => $runner->counts(request('batch'))]);
    })->name('ai-jobs.tick')->middleware('throttle:30,1');

    // Quản trị hệ thống -- chỉ chủ hệ thống (users.id = 1).
    Route::prefix('system')->name('system.')->middleware('owner')->group(function () {
        Route::get('/', [SystemController::class, 'index'])->name('index');
        Route::post('config', [SystemController::class, 'updateConfig'])->name('config');
        Route::post('sync', [SystemController::class, 'updateSync'])->name('sync');
        Route::post('token', [SystemController::class, 'regenerateToken'])->name('token');
        Route::post('command', [SystemController::class, 'runCommand'])->name('command');
        Route::post('backups', [SystemController::class, 'backupCreate'])->name('backups.create');
        Route::post('backups/upload', [SystemController::class, 'backupUpload'])->name('backups.upload');
        Route::get('backups/{name}', [SystemController::class, 'backupDownload'])->name('backups.download');
        Route::post('backups/{name}/restore', [SystemController::class, 'backupRestore'])->name('backups.restore');
        Route::delete('backups/{name}', [SystemController::class, 'backupDelete'])->name('backups.delete');
        Route::post('ai', [SystemController::class, 'updateAi'])->name('ai');
        Route::post('ai/test', [SystemController::class, 'aiTest'])->name('ai.test');
        Route::post('ai/test-job', [SystemController::class, 'aiTestJob'])->name('ai.test-job');
        Route::post('ai/tick', [SystemController::class, 'aiTick'])->name('ai.tick');
        Route::post('ai/jobs/{job}/retry', [SystemController::class, 'aiRetryJob'])->name('ai.retry');
        Route::post('ai/jobs/retry-failed', [SystemController::class, 'aiRetryFailed'])->name('ai.retry-failed');

        // Thư viện dạng bài (V2 bước 1).
        Route::get('question-types', [\App\Http\Controllers\Admin\QuestionTypeController::class, 'index'])->name('question-types.index');
        Route::match(['get', 'post'], 'question-types/{code}', [\App\Http\Controllers\Admin\QuestionTypeController::class, 'show'])->name('question-types.show');
        Route::post('question-types/{code}/grade', [\App\Http\Controllers\Admin\QuestionTypeController::class, 'grade'])->name('question-types.grade');
        Route::post('question-types/{code}/toggle', [\App\Http\Controllers\Admin\QuestionTypeController::class, 'toggle'])->name('question-types.toggle');
        Route::post('question-types/{code}/scoring', [\App\Http\Controllers\Admin\QuestionTypeController::class, 'scoring'])->name('question-types.scoring');
    });
});

// ============================================================ CỔNG GIÁO VIÊN
use App\Http\Controllers\Teacher\AuthController as TeacherAuthController;
use App\Http\Controllers\Teacher\DashboardController as TeacherDashboardController;

Route::prefix('giao-vien')->name('teacher.')->group(function () {
    Route::get('dang-nhap', [TeacherAuthController::class, 'showLogin'])->name('login');
    Route::post('dang-nhap', [TeacherAuthController::class, 'login'])->name('login.submit');
    Route::post('dang-xuat', [TeacherAuthController::class, 'logout'])->name('logout');

    Route::middleware('teacher')->group(function () {
        Route::get('/', [TeacherDashboardController::class, 'index'])->name('dashboard');



        // Lớp học & giao bài
        Route::get('lop-hoc', [\App\Http\Controllers\Teacher\ClassroomController::class, 'index'])->name('classrooms.index');
        Route::get('lop-hoc/{classroom}', [\App\Http\Controllers\Teacher\ClassroomController::class, 'show'])->name('classrooms.show');
        Route::put('lop-hoc/{classroom}', [\App\Http\Controllers\Teacher\ClassroomController::class, 'update'])->name('classrooms.update');
        Route::get('hoc-vien/file-mau', [\App\Http\Controllers\Teacher\ClassroomController::class, 'template'])->name('students.import-template');
        Route::post('lop-hoc/{classroom}/nhap-hoc-vien', [\App\Http\Controllers\Teacher\ClassroomController::class, 'import'])->name('classrooms.import');
        Route::post('lop-hoc/{classroom}/nhap-hoc-vien/luu', [\App\Http\Controllers\Teacher\ClassroomController::class, 'importSave'])->name('classrooms.import.commit');
        Route::get('lop-hoc/{classroom}/nhap-hoc-vien/tai-khoan.csv', [\App\Http\Controllers\Teacher\ClassroomController::class, 'credentials'])->name('classrooms.import.credentials');
        Route::get('lop-hoc/{classroom}/hoc-vien/tim', [\App\Http\Controllers\Teacher\ClassroomController::class, 'searchStudents'])->name('classrooms.students.search')->middleware('throttle:120,1');
        Route::post('lop-hoc/{classroom}/hoc-vien/them-co-san', [\App\Http\Controllers\Teacher\ClassroomController::class, 'addExisting'])->name('classrooms.students.add-existing');
        Route::put('lop-hoc/{classroom}/lich-bai-hoc', [\App\Http\Controllers\Teacher\ClassroomController::class, 'lessonSchedule'])->name('classrooms.lesson-schedule');
        Route::post('lop-hoc/{classroom}/hoc-vien/{student}/cap-lai-mat-khau', [\App\Http\Controllers\Teacher\ClassroomController::class, 'resetPassword'])->name('classrooms.students.reset');
        Route::post('lop-hoc/{classroom}/hoc-vien', [\App\Http\Controllers\Teacher\ClassroomController::class, 'addStudents'])->name('classrooms.students.add');
        Route::delete('lop-hoc/{classroom}/hoc-vien/{student}', [\App\Http\Controllers\Teacher\ClassroomController::class, 'removeStudent'])->name('classrooms.students.remove');
        Route::get('lop-hoc/{classroom}/nang-luc', [\App\Http\Controllers\Teacher\ClassroomController::class, 'competency'])->name('classrooms.competency');
        Route::get('lop-hoc/{classroom}/de-thi-thu', [\App\Http\Controllers\Teacher\AssignmentController::class, 'mock'])->name('assignments.mock');
        Route::post('lop-hoc/{classroom}/de-thi-thu', [\App\Http\Controllers\Teacher\AssignmentController::class, 'storeMock'])->name('assignments.mock.store');
        Route::get('lop-hoc/{classroom}/giao-bai', [\App\Http\Controllers\Teacher\AssignmentController::class, 'create'])->name('assignments.create');
        Route::post('lop-hoc/{classroom}/giao-bai', [\App\Http\Controllers\Teacher\AssignmentController::class, 'store'])->name('assignments.store');
        Route::get('bai-giao/{assignment}', [\App\Http\Controllers\Teacher\AssignmentController::class, 'show'])->name('assignments.show');
        Route::delete('bai-giao/{assignment}', [\App\Http\Controllers\Teacher\AssignmentController::class, 'destroy'])->name('assignments.destroy');
        Route::get('bai-giao/{assignment}/xuat-excel', [\App\Http\Controllers\Teacher\AssignmentController::class, 'export'])->name('assignments.export');
        Route::get('bai-giao/{assignment}/bai-lam/{test}', [\App\Http\Controllers\Teacher\AssignmentController::class, 'review'])->name('assignments.review');
        Route::put('bai-giao/{assignment}/cham-lai/{attempt}', [\App\Http\Controllers\Teacher\AssignmentController::class, 'saveReview'])->name('assignments.review.save');
        Route::get('bai-giao/{assignment}/ghi-am/{attempt}/{code}', [\App\Http\Controllers\Teacher\AssignmentController::class, 'recording'])->name('assignments.recording');

        Route::get('khoa-hoc', [\App\Http\Controllers\Teacher\CourseController::class, 'index'])->name('courses.index');
        Route::prefix('khoa-hoc/{course}')->name('ai-import.')->group(function () {
        Route::get('dung-bai-ai', [\App\Http\Controllers\Teacher\CustomExerciseController::class, 'aiCreate'])->name('create');
        Route::post('dung-bai-ai', [\App\Http\Controllers\Teacher\CustomExerciseController::class, 'aiStore'])->name('store');
        Route::get('dung-bai-ai/file-mau.docx', [\App\Http\Controllers\Teacher\CustomExerciseController::class, 'aiTemplate'])->name('template');
        Route::get('dung-bai-ai/{import}', [\App\Http\Controllers\Teacher\CustomExerciseController::class, 'aiShow'])->name('show');
        Route::delete('dung-bai-ai/{import}', [\App\Http\Controllers\Teacher\CustomExerciseController::class, 'aiDestroy'])->name('destroy');
        Route::post('dung-bai-ai/{import}/tai-lieu', [\App\Http\Controllers\Teacher\CustomExerciseController::class, 'aiDocument'])->name('document');
        Route::post('dung-bai-ai/{import}/file-nghe', [\App\Http\Controllers\Teacher\CustomExerciseController::class, 'aiAudio'])->name('audio');
        Route::get('dung-bai-ai/{import}/file-nghe/{audioId}', [\App\Http\Controllers\Teacher\CustomExerciseController::class, 'aiAudioFile'])->name('audio-file');
        Route::post('dung-bai-ai/{import}/bat-dau', [\App\Http\Controllers\Teacher\CustomExerciseController::class, 'aiBegin'])->name('begin');
        Route::post('dung-bai-ai/{import}/tick', [\App\Http\Controllers\Teacher\CustomExerciseController::class, 'aiTick'])->name('tick')->middleware('throttle:40,1');
        Route::put('dung-bai-ai/{import}/phan/{section}', [\App\Http\Controllers\Teacher\CustomExerciseController::class, 'aiUpdateSection'])->name('section.update')->whereNumber('section');
        Route::post('dung-bai-ai/{import}/phan/{section}/file-nghe', [\App\Http\Controllers\Teacher\CustomExerciseController::class, 'aiSectionAudio'])->name('section.audio')->whereNumber('section');
        Route::post('dung-bai-ai/{import}/phan/{section}/giong-ai', [\App\Http\Controllers\Teacher\CustomExerciseController::class, 'aiSectionTts'])->name('section.tts')->whereNumber('section');
        Route::post('dung-bai-ai/{import}/phan/{section}/anh', [\App\Http\Controllers\Teacher\CustomExerciseController::class, 'aiSectionImage'])->name('section.image')->whereNumber('section');
        Route::get('dung-bai-ai/{import}/phan/{section}/anh', [\App\Http\Controllers\Teacher\CustomExerciseController::class, 'aiSectionImageFile'])->name('section.image-file')->whereNumber('section');
        Route::post('dung-bai-ai/{import}/phan/{section}/dung-lai', [\App\Http\Controllers\Teacher\CustomExerciseController::class, 'aiRebuild'])->name('section.rebuild')->whereNumber('section');
        Route::post('dung-bai-ai/{import}/dung-lai-tat-ca', [\App\Http\Controllers\Teacher\CustomExerciseController::class, 'aiRebuildAll'])->name('rebuild-all');
        Route::post('dung-bai-ai/{import}/phan/{section}/di-chuyen', [\App\Http\Controllers\Teacher\CustomExerciseController::class, 'aiMoveSection'])->name('section.move')->whereNumber('section');
        Route::delete('dung-bai-ai/{import}/phan/{section}', [\App\Http\Controllers\Teacher\CustomExerciseController::class, 'aiDeleteSection'])->name('section.destroy')->whereNumber('section');
        Route::post('dung-bai-ai/{import}/file-nghe/{audioId}/chep-loi', [\App\Http\Controllers\Teacher\CustomExerciseController::class, 'aiTranscribe'])->name('audio.transcribe');
        Route::post('dung-bai-ai/{import}/luu', [\App\Http\Controllers\Teacher\CustomExerciseController::class, 'aiSave'])->name('save');
        });
        Route::prefix('khoa-hoc/{course}')->name('custom.')->group(function () {
        Route::get('bai-tap-tuy-chinh', [\App\Http\Controllers\Teacher\CustomExerciseController::class, 'index'])->name('index');
        Route::post('bai-tap-tuy-chinh', [\App\Http\Controllers\Teacher\CustomExerciseController::class, 'store'])->name('store');
        Route::get('bai-tap-tuy-chinh/{exercise}', [\App\Http\Controllers\Teacher\CustomExerciseController::class, 'edit'])->name('edit');
        Route::put('bai-tap-tuy-chinh/{exercise}', [\App\Http\Controllers\Teacher\CustomExerciseController::class, 'update'])->name('update');
        Route::delete('bai-tap-tuy-chinh/{exercise}', [\App\Http\Controllers\Teacher\CustomExerciseController::class, 'destroy'])->name('destroy');
        Route::patch('bai-tap-tuy-chinh/{exercise}/trang-thai', [\App\Http\Controllers\Teacher\CustomExerciseController::class, 'quickStatus'])->name('status');
        Route::post('bai-tap-tuy-chinh/{exercise}/nhan-ban', [\App\Http\Controllers\Teacher\CustomExerciseController::class, 'duplicate'])->name('duplicate');
        Route::post('bai-tap-tuy-chinh/{exercise}/cham-lai', [\App\Http\Controllers\Teacher\CustomExerciseController::class, 'regrade'])->name('regrade');
        Route::get('bai-tap-tuy-chinh/{exercise}/xem-truoc', [\App\Http\Controllers\Teacher\CustomExerciseController::class, 'preview'])->name('preview');
        Route::post('bai-tap-tuy-chinh/{exercise}/phan', [\App\Http\Controllers\Teacher\CustomExerciseController::class, 'itemStore'])->name('items.store');
        Route::put('bai-tap-tuy-chinh/{exercise}/phan/{item}', [\App\Http\Controllers\Teacher\CustomExerciseController::class, 'itemUpdate'])->name('items.update');
        Route::delete('bai-tap-tuy-chinh/{exercise}/phan/{item}', [\App\Http\Controllers\Teacher\CustomExerciseController::class, 'itemDestroy'])->name('items.destroy');
        Route::post('bai-tap-tuy-chinh/{exercise}/phan/{item}/di-chuyen', [\App\Http\Controllers\Teacher\CustomExerciseController::class, 'itemMove'])->name('items.move');
        Route::post('bai-tap-tuy-chinh/{exercise}/phan/{item}/ai', [\App\Http\Controllers\Teacher\CustomExerciseController::class, 'itemAi'])->name('items.ai');
        Route::post('bai-tap-tuy-chinh/{exercise}/ai-trang-thai', [\App\Http\Controllers\Teacher\CustomExerciseController::class, 'itemAiStatus'])->name('items.ai-status')->middleware('throttle:60,1');
        Route::post('bai-tap-tuy-chinh/{exercise}/phan/{item}/file-nghe', [\App\Http\Controllers\Teacher\CustomExerciseController::class, 'itemAudio'])->name('items.audio');
        Route::get('bai-tap-tuy-chinh/{exercise}/phan/{item}/file-nghe', [\App\Http\Controllers\Teacher\CustomExerciseController::class, 'itemAudioFile'])->name('items.audio-file');
        Route::post('bai-tap-tuy-chinh/{exercise}/phan/{item}/anh', [\App\Http\Controllers\Teacher\CustomExerciseController::class, 'itemImage'])->name('items.image');
        Route::get('bai-tap-tuy-chinh/{exercise}/phan/{item}/anh', [\App\Http\Controllers\Teacher\CustomExerciseController::class, 'itemImageFile'])->name('items.image-file');
        });
        Route::name('classrooms.')->group(function () {
        Route::get('lop-hoc/{classroom}/bai-tap/{exercise}/ket-qua', [\App\Http\Controllers\Teacher\ClassroomController::class, 'customOpen'])->name('custom.open');
        Route::get('lop-hoc/{classroom}/bai-tuy-chinh/{cce}', [\App\Http\Controllers\Teacher\ClassroomController::class, 'customResults'])->name('custom.results');
        Route::put('lop-hoc/{classroom}/bai-tuy-chinh/{cce}', [\App\Http\Controllers\Teacher\ClassroomController::class, 'customUpdateAssignment'])->name('custom.update');
        Route::delete('lop-hoc/{classroom}/bai-tuy-chinh/{cce}', [\App\Http\Controllers\Teacher\ClassroomController::class, 'customUnassign'])->name('custom.unassign');
        Route::get('lop-hoc/{classroom}/bai-tuy-chinh/{cce}/bai-lam/{attempt}', [\App\Http\Controllers\Teacher\ClassroomController::class, 'customReview'])->name('custom.review');
        Route::put('lop-hoc/{classroom}/bai-tuy-chinh/{cce}/bai-lam/{attempt}', [\App\Http\Controllers\Teacher\ClassroomController::class, 'customSaveReview'])->name('custom.review.save');
        Route::get('lop-hoc/{classroom}/bai-tuy-chinh/{cce}/bai-lam/{attempt}/ghi-am/{item}', [\App\Http\Controllers\Teacher\ClassroomController::class, 'customRecording'])->name('custom.recording');
        });
        Route::prefix('khoa-hoc/{course}')->name('lessons.')->group(function () {
        Route::get('bai-hoc', [\App\Http\Controllers\Teacher\LessonController::class, 'index'])->name('index');
        Route::post('bai-hoc', [\App\Http\Controllers\Teacher\LessonController::class, 'store'])->name('store');
        Route::post('bai-hoc/sap-xep', [\App\Http\Controllers\Teacher\LessonController::class, 'reorder'])->name('reorder');
        Route::get('bai-hoc/{lesson}', [\App\Http\Controllers\Teacher\LessonController::class, 'edit'])->name('edit');
        Route::put('bai-hoc/{lesson}', [\App\Http\Controllers\Teacher\LessonController::class, 'update'])->name('update');
        Route::delete('bai-hoc/{lesson}', [\App\Http\Controllers\Teacher\LessonController::class, 'destroy'])->name('destroy');
        Route::post('bai-hoc/{lesson}/ai-tao-bai-tap', [\App\Http\Controllers\Teacher\LessonController::class, 'aiCompose'])->name('ai-compose');
        Route::post('bai-hoc/tu-vung-ai', [\App\Http\Controllers\Teacher\LessonController::class, 'aiVocabularyAll'])->name('vocabulary.ai-all');
        Route::put('bai-hoc/{lesson}/tu-vung', [\App\Http\Controllers\Teacher\LessonController::class, 'saveVocabulary'])->name('vocabulary');
        Route::post('bai-hoc/{lesson}/tu-vung/ai', [\App\Http\Controllers\Teacher\LessonController::class, 'aiVocabulary'])->name('vocabulary.ai');
        Route::post('bai-hoc/{lesson}/video', [\App\Http\Controllers\Teacher\LessonController::class, 'uploadVideo'])->name('video.upload');
        Route::post('bai-hoc/{lesson}/video-bunny', [\App\Http\Controllers\Teacher\LessonController::class, 'addBunny'])->name('video.bunny');
        Route::put('bai-hoc/{lesson}/video/{video}', [\App\Http\Controllers\Teacher\LessonController::class, 'updateVideo'])->name('video.update');
        Route::delete('bai-hoc/{lesson}/video/{video}', [\App\Http\Controllers\Teacher\LessonController::class, 'destroyVideo'])->name('video.destroy');
        Route::get('bai-hoc/{lesson}/video/{video}/xem', [\App\Http\Controllers\Teacher\LessonController::class, 'previewVideo'])->name('video.preview');
        Route::post('bai-hoc/{lesson}/tai-lieu', [\App\Http\Controllers\Teacher\LessonController::class, 'uploadMaterial'])->name('material.upload');
        Route::delete('bai-hoc/{lesson}/tai-lieu/{material}', [\App\Http\Controllers\Teacher\LessonController::class, 'destroyMaterial'])->name('material.destroy');
        });

    });
});

// Catch-all: resolves to a post or a static page (keeps the old site's flat URL style).
Route::get('/{slug}', [PublicController::class, 'show'])->name('public.show')->where('slug', '[a-z0-9-]+');
