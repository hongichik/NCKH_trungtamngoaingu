<?php

namespace App\Support\QuestionTypes\Catalog;

/** Dạng bài TOEIC Listening & Reading (Part 1–7). */
class Toeic
{
    public static function types(): array
    {
        $t = fn (array $d) => $d + ['standard' => 'toeic', 'lang' => 'en', 'engine' => 'choice'];
        $ls = ['scale' => 'toeic_listening'];
        $rs = ['scale' => 'toeic_reading'];

        return [
            $t(['code' => 'toeic.part1_photographs', 'name' => 'TOEIC Part 1 — Photographs (mô tả tranh)', 'skill' => 'listening', 'scoring' => $ls,
                'description' => 'Nghe 4 câu mô tả, chọn câu đúng nhất với bức ảnh. Lựa chọn không in trên đề.',
                'sample' => ['instructions' => 'Look at the picture and listen to four statements. Choose the one that best describes the picture.',
                    'stimulus' => ['image' => '/images/qtypes/scene.svg', 'transcript' => '(A) A man is closing a door. (B) A plant has been placed near the window. (C) Some people are eating lunch. (D) The desk is being painted.'],
                    'questions' => [['id' => '1', 'options' => ['(A)', '(B)', '(C)', '(D)'], 'answer' => 'B']]]]),
            $t(['code' => 'toeic.part2_question_response', 'name' => 'TOEIC Part 2 — Question–Response', 'skill' => 'listening', 'scoring' => $ls,
                'sample' => ['instructions' => 'Listen to a question and three responses. Choose the best response.',
                    'stimulus' => ['transcript' => 'Where should I put these boxes? (A) By Friday afternoon. (B) In the storage room, please. (C) Yes, I put them.'],
                    'questions' => [['id' => '1', 'options' => ['(A)', '(B)', '(C)'], 'answer' => 'B']]]]),
            $t(['code' => 'toeic.part3_conversations', 'name' => 'TOEIC Part 3 — Conversations', 'skill' => 'listening', 'scoring' => $ls,
                'sample' => ['instructions' => 'Listen to the conversation and answer the questions.',
                    'stimulus' => ['transcript' => "W: Hi, I'd like to change my flight to Singapore from Monday to Wednesday. M: Let me check. There's a seat on the Wednesday morning flight, but there's a twenty-dollar fee. W: That's fine. Please book it."],
                    'questions' => [
                        ['id' => '1', 'prompt' => 'What does the woman want to do?', 'options' => ['Cancel a hotel booking', 'Change a flight date', 'Upgrade her seat', 'Buy a new suitcase'], 'answer' => 'B'],
                        ['id' => '2', 'prompt' => 'How much is the fee?', 'options' => ['$10', '$20', '$30', '$40'], 'answer' => 'B'],
                    ]]]),
            $t(['code' => 'toeic.part4_talks', 'name' => 'TOEIC Part 4 — Talks', 'skill' => 'listening', 'scoring' => $ls,
                'sample' => ['instructions' => 'Listen to the talk and answer the questions.',
                    'stimulus' => ['transcript' => 'Attention, shoppers. Our store will close in fifteen minutes. Please bring your items to the registers at the front. Tomorrow we open at nine a.m. with a special sale on kitchen products.'],
                    'questions' => [
                        ['id' => '1', 'prompt' => 'Where is the announcement being made?', 'options' => ['At an airport', 'In a store', 'At a train station', 'In a museum'], 'answer' => 'B'],
                        ['id' => '2', 'prompt' => 'What will happen tomorrow?', 'options' => ['A sale will take place', 'The store will close early', 'New staff will start', 'Prices will rise'], 'answer' => 'A'],
                    ]]]),
            $t(['code' => 'toeic.part5_incomplete_sentences', 'name' => 'TOEIC Part 5 — Incomplete sentences', 'skill' => 'grammar', 'scoring' => $rs,
                'sample' => ['instructions' => 'Choose the word or phrase that best completes the sentence.', 'questions' => [
                    ['id' => '1', 'prompt' => 'The report must be submitted ___ Friday at the latest.', 'options' => ['by', 'until', 'since', 'during'], 'answer' => 'A'],
                    ['id' => '2', 'prompt' => 'Ms. Park was ___ to the position of sales manager last month.', 'options' => ['promote', 'promoting', 'promoted', 'promotion'], 'answer' => 'C'],
                ]]]),
            $t(['code' => 'toeic.part6_text_completion', 'name' => 'TOEIC Part 6 — Text completion', 'skill' => 'reading', 'scoring' => $rs, 'engine' => 'gap_select',
                'sample' => ['instructions' => 'Read the text and choose the best answer for each blank.',
                    'text' => "To: All staff\nSubject: Office move\n\nOur office will [[1]] to the fifth floor next Monday. Please pack your desk items [[2]] Friday afternoon. [[3]]",
                    'gaps' => [
                        '1' => ['options' => ['move', 'moves', 'moving', 'moved'], 'answer' => 'move'],
                        '2' => ['options' => ['by', 'at', 'on', 'in'], 'answer' => 'by'],
                        '3' => ['options' => ['Boxes will be provided at reception.', 'The fifth floor was built in 1990.', 'Lunch is not included.', 'He works in sales.'], 'answer' => 'Boxes will be provided at reception.'],
                    ]]]),
            $t(['code' => 'toeic.part7_single', 'name' => 'TOEIC Part 7 — Single passage', 'skill' => 'reading', 'scoring' => $rs,
                'sample' => ['instructions' => 'Read the notice and answer the questions.',
                    'stimulus' => ['title' => 'NOTICE', 'passage' => 'The staff car park will be closed on Saturday 12 May for repainting. Employees working that day may park at the City Mall car park free of charge. Please show your staff card to the attendant.'],
                    'questions' => [
                        ['id' => '1', 'prompt' => 'Why will the car park be closed?', 'options' => ['For a staff event', 'For repainting', 'For security checks', 'Because of a holiday'], 'answer' => 'B'],
                        ['id' => '2', 'prompt' => 'What must employees do at City Mall?', 'options' => ['Pay a small fee', 'Show a staff card', 'Arrive before 8 a.m.', 'Register online'], 'answer' => 'B'],
                    ]]]),
            $t(['code' => 'toeic.part7_multiple', 'name' => 'TOEIC Part 7 — Double / triple passages', 'skill' => 'reading', 'scoring' => $rs,
                'description' => 'Hai hoặc ba văn bản liên quan (email + lịch + hoá đơn…); có câu hỏi cần đối chiếu nhiều văn bản.',
                'sample' => ['instructions' => 'Read the e-mail and the schedule, then answer the questions.',
                    'stimulus' => ['passage' => "E-MAIL\nFrom: Laura Kim\nTo: Tom Reed\nHi Tom, I can't attend the Tuesday workshop because of a client meeting. Could you register me for the Thursday session instead? Thanks, Laura\n\nSCHEDULE — Presentation Skills Workshop\nTuesday 10:00–12:00 · Room 3\nThursday 14:00–16:00 · Room 5"],
                    'questions' => [
                        ['id' => '1', 'prompt' => 'Why can Laura not attend on Tuesday?', 'options' => ['She is on holiday', 'She has a client meeting', 'The room is too small', 'She is ill'], 'answer' => 'B'],
                        ['id' => '2', 'prompt' => 'Where will Laura attend the workshop?', 'options' => ['Room 3', 'Room 5', 'Online', 'At the client\'s office'], 'answer' => 'B'],
                    ]]]),
        ];
    }
}
