<?php

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CertificateController;
use App\Http\Controllers\Admin\ContactController as AdminContactController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ExamController as AdminExamController;
use App\Http\Controllers\Admin\ExamRegistrationController as AdminExamRegistrationController;
use App\Http\Controllers\Admin\GalleryController as AdminGalleryController;
use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\PostController;
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
Route::get('/', [PublicController::class, 'home'])->name('home');
Route::get('/tin-tuc', [PublicController::class, 'blogIndex'])->name('blog.index');
Route::get('/danh-muc/{category:slug}', [PublicController::class, 'category'])->name('category.show');
Route::get('/thu-vien-anh', [GalleryController::class, 'index'])->name('galleries.index');
Route::get('/thu-vien-anh/{gallery:slug}', [GalleryController::class, 'show'])->name('galleries.show');
Route::get('/tra-cuu-chung-chi/{serialNumber?}', [CertificateLookupController::class, 'show'])->name('certificates.lookup');
Route::get('/lien-he', [ContactController::class, 'create'])->name('contact.create');
Route::post('/lien-he', [ContactController::class, 'store'])->name('contact.store');

// Candidate portal: register/login, then complete profile before registering for an exam.
Route::get('/dang-ky-chung-chi', [PortalAuthController::class, 'show'])->name('portal.auth');
Route::post('/dang-ky-chung-chi/dang-ky', [PortalAuthController::class, 'register'])->name('portal.register');
Route::post('/dang-ky-chung-chi/dang-nhap', [PortalAuthController::class, 'login'])->name('portal.login');
Route::post('/thi-sinh/dang-xuat', [PortalAuthController::class, 'logout'])->name('portal.logout')->middleware('student.auth');

Route::prefix('thi-sinh')->name('portal.')->middleware('student.auth')->group(function () {
    Route::get('thong-tin', [PortalProfileController::class, 'edit'])->name('profile.edit');
    // POST, not PUT: this form has file inputs, and PHP silently drops
    // multipart fields (including _token) on a spoofed PUT request.
    Route::post('thong-tin', [PortalProfileController::class, 'update'])->name('profile.update');

    Route::get('dang-ky-thi/{type}', [ExamRegistrationController::class, 'show'])->whereIn('type', ['hsk', 'cntt'])->name('exam.show');
    Route::post('dang-ky-thi/{type}', [ExamRegistrationController::class, 'store'])->whereIn('type', ['hsk', 'cntt'])->name('exam.store');

    Route::get('ket-qua', [PortalResultController::class, 'index'])->name('results.index');
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
    Route::post('posts/upload-image', [PostController::class, 'uploadImage'])->name('posts.upload-image');
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

    // User & permission management -- restricted to the highest-level (super) admin only,
    // so a limited account can never grant itself (or anyone else) more access.
    Route::middleware('super-admin')->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::get('users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });
});

// Catch-all: resolves to a post or a static page (keeps the old site's flat URL style).
Route::get('/{slug}', [PublicController::class, 'show'])->name('public.show')->where('slug', '[a-z0-9-]+');
