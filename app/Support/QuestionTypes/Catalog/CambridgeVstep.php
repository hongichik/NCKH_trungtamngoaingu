<?php

namespace App\Support\QuestionTypes\Catalog;

/** Dạng bài Cambridge (KET / PET / FCE), VSTEP và luyện phát âm tiếng Anh. */
class CambridgeVstep
{
    public static function types(): array
    {
        $c = fn (array $d) => $d + ['standard' => 'cambridge', 'lang' => 'en'];
        $v = fn (array $d) => $d + ['standard' => 'vstep', 'lang' => 'en', 'scoring' => ['scale' => 'vstep']];
        $p = fn (array $d) => $d + ['standard' => 'pronunciation', 'lang' => 'en', 'skill' => 'pronunciation'];

        return [
            // ------------------------------------------------------------ CAMBRIDGE
            $c(['code' => 'cambridge.mc_cloze', 'name' => 'Cambridge — Multiple-choice cloze', 'skill' => 'vocab', 'engine' => 'gap_select',
                'sample' => ['instructions' => 'For each gap, choose the correct answer.',
                    'text' => 'Last summer I [[1]] part in a photography course. At first I found it hard to [[2]] good pictures, but my teacher [[3]] me a lot.',
                    'gaps' => ['1' => ['options' => ['took', 'made', 'did', 'got'], 'answer' => 'took'], '2' => ['options' => ['do', 'make', 'take', 'have'], 'answer' => 'take'], '3' => ['options' => ['helped', 'let', 'told', 'said'], 'answer' => 'helped']]]]),
            $c(['code' => 'cambridge.open_cloze', 'name' => 'Cambridge — Open cloze', 'skill' => 'grammar', 'engine' => 'gap_text', 'options' => ['max_words' => 1],
                'sample' => ['instructions' => 'Write ONE word in each gap.',
                    'text' => 'I have been learning English [[1]] I was seven. My sister is better [[2]] me at speaking, but I am good [[3]] writing.',
                    'answers' => ['1' => ['since'], '2' => ['than'], '3' => ['at']]]]),
            $c(['code' => 'cambridge.word_formation', 'name' => 'Cambridge — Word formation', 'skill' => 'vocab', 'engine' => 'gap_text', 'options' => ['max_words' => 1],
                'sample' => ['instructions' => 'Use the word in CAPITALS to form a word that fits in the gap.',
                    'text' => "1. The film was really [[1]]. (ENTERTAIN)\n2. She spoke with great [[2]]. (CONFIDENT)\n3. It is [[3]] to swim here — the water is too deep. (SAFE)",
                    'answers' => ['1' => ['entertaining'], '2' => ['confidence'], '3' => ['unsafe']]]]),
            $c(['code' => 'cambridge.key_word_transformation', 'name' => 'Cambridge — Key word transformation', 'skill' => 'grammar', 'engine' => 'short_text', 'options' => ['max_words' => 5],
                'description' => 'Viết 2–5 từ, bắt buộc dùng từ khoá, giữ nguyên nghĩa.',
                'sample' => ['instructions' => 'Complete the second sentence using the word given. Do not change the word given. Use between two and five words.', 'questions' => [
                    ['id' => '1', 'prompt' => "I haven't seen Tom for three years. (LAST)\nThe ____ Tom was three years ago.", 'keyword' => 'last', 'answers' => ['last time I saw']],
                    ['id' => '2', 'prompt' => "It wasn't necessary for us to wait. (HAVE)\nWe ____ wait.", 'keyword' => 'have', 'answers' => ["didn't have to", 'did not have to']],
                ]]]),
            $c(['code' => 'cambridge.gapped_text', 'name' => 'Cambridge — Gapped text (điền câu vào đoạn)', 'skill' => 'reading', 'engine' => 'gap_select',
                'sample' => ['instructions' => 'Choose the sentence (A–D) which fits each gap. There is one extra sentence.',
                    'text' => 'Mia had always wanted to climb a mountain. [[1]] She trained every weekend for six months. [[2]] When she finally reached the top, she cried with happiness.',
                    'options' => ['It was the hardest thing she had ever done.', 'Last year she decided it was time to try.', 'Her brother hates sport.', 'She often ran up the hills near her home.'],
                    'answers' => ['1' => 'B', '2' => 'D']]]),
            $c(['code' => 'cambridge.multiple_matching', 'name' => 'Cambridge — Multiple matching', 'skill' => 'reading', 'engine' => 'matching', 'options' => ['reuse' => true],
                'sample' => ['instructions' => 'Which person says each statement?',
                    'stimulus' => ['passage' => "A  Sam: I started playing the guitar when I was ten. Now I play in a band at weekends.\nB  Lily: I've never been musical, but I love going to concerts with friends.\nC  Jack: I teach piano to children. Seeing them improve is the best part of my job."],
                    'items' => [['id' => '1', 'text' => 'This person enjoys watching others perform.'], ['id' => '2', 'text' => 'This person performs regularly.'], ['id' => '3', 'text' => 'This person helps young people learn.']],
                    'options' => [['key' => 'A', 'text' => 'Sam'], ['key' => 'B', 'text' => 'Lily'], ['key' => 'C', 'text' => 'Jack']], 'answers' => ['1' => 'B', '2' => 'A', '3' => 'C']]]),
            $c(['code' => 'cambridge.long_text_mc', 'name' => 'Cambridge — Long text multiple choice', 'skill' => 'reading', 'engine' => 'choice',
                'sample' => ['instructions' => 'Read the text and choose the best answer.',
                    'stimulus' => ['passage' => "When I moved to Canada at fourteen, I didn't speak much English. The first months were difficult: I often ate lunch alone and avoided speaking in class. Everything changed when my teacher asked me to help with the school newspaper. Writing short articles gave me confidence, and soon I had made friends with the other writers."],
                    'questions' => [
                        ['id' => '1', 'prompt' => 'How did the writer feel in the first months?', 'options' => ['excited', 'lonely', 'angry', 'bored'], 'answer' => 'B'],
                        ['id' => '2', 'prompt' => 'What helped the writer most?', 'options' => ['joining a sports team', 'working on the school newspaper', 'taking extra classes', 'moving to a new school'], 'answer' => 'B'],
                    ]]]),

            // ------------------------------------------------------------ VSTEP
            $v(['code' => 'vstep.listening', 'name' => 'VSTEP — Nghe hiểu (thông báo, hội thoại, bài giảng)', 'skill' => 'listening', 'engine' => 'choice',
                'description' => '35 câu, 3 phần; quy đổi cùng 3 kỹ năng còn lại ra bậc 3–5.',
                'sample' => ['instructions' => 'Listen and choose the correct answer.',
                    'stimulus' => ['transcript' => 'Good afternoon, passengers. The train to Hai Phong will leave from platform four instead of platform two. We apologise for the change.'],
                    'questions' => [['id' => '1', 'prompt' => 'Which platform will the train leave from?', 'options' => ['Platform 2', 'Platform 3', 'Platform 4', 'Platform 5'], 'answer' => 'C']]]]),
            $v(['code' => 'vstep.reading', 'name' => 'VSTEP — Đọc hiểu', 'skill' => 'reading', 'engine' => 'choice',
                'sample' => ['instructions' => 'Read the passage and choose the correct answer.',
                    'stimulus' => ['passage' => 'Urban gardens are becoming popular in Vietnamese cities. Residents grow vegetables on balconies and rooftops, which reduces their food costs and gives them a chance to relax after work.'],
                    'questions' => [
                        ['id' => '1', 'prompt' => 'Where do residents grow vegetables?', 'options' => ['In parks', 'On balconies and rooftops', 'In schools', 'On farms outside the city'], 'answer' => 'B'],
                        ['id' => '2', 'prompt' => 'The word "reduces" is closest in meaning to ___.', 'options' => ['increases', 'lowers', 'keeps', 'pays'], 'answer' => 'B'],
                    ]]]),
            $v(['code' => 'vstep.writing_task1', 'name' => 'VSTEP — Viết Task 1 (thư / email, 120 từ)', 'skill' => 'writing', 'engine' => 'writing', 'grading' => 'ai',
                'options' => ['criteria' => ['Hoàn thành nhiệm vụ', 'Tổ chức bài', 'Từ vựng', 'Ngữ pháp']],
                'sample' => ['prompt' => 'Your friend has asked for advice about learning English. Write an email (about 120 words) giving your advice.', 'min_words' => 120, 'time_minutes' => 20]]),
            $v(['code' => 'vstep.writing_task2', 'name' => 'VSTEP — Viết Task 2 (bài luận, 250 từ)', 'skill' => 'writing', 'engine' => 'writing', 'grading' => 'ai',
                'options' => ['criteria' => ['Hoàn thành nhiệm vụ', 'Tổ chức bài', 'Từ vựng', 'Ngữ pháp']],
                'sample' => ['prompt' => 'Many young people prefer to work for large companies rather than start their own business. What are the reasons? Give your opinion. Write at least 250 words.', 'min_words' => 250, 'time_minutes' => 40]]),
            $v(['code' => 'vstep.speaking', 'name' => 'VSTEP — Nói (3 phần: tương tác, thảo luận giải pháp, phát triển chủ đề)', 'skill' => 'speaking', 'engine' => 'speaking', 'grading' => 'ai',
                'options' => ['criteria' => ['Ngữ pháp', 'Từ vựng', 'Phát âm', 'Độ trôi chảy', 'Phát triển ý'], 'prep_seconds' => 60, 'speak_seconds' => 180],
                'sample' => ['prompt' => 'Part 2 — Situation: Your class is planning a weekend trip. Three options are suggested: a beach, a mountain, or a museum in the city. Which do you think is the best choice? Explain why.']]),

            // ------------------------------------------------------------ PHÁT ÂM
            $p(['code' => 'pron.minimal_pairs', 'name' => 'Phát âm — Cặp âm tối thiểu (ship / sheep)', 'engine' => 'choice',
                'description' => 'Nghe một từ, chọn từ nghe được trong cặp âm gần giống.',
                'sample' => ['instructions' => 'Listen and choose the word you hear.', 'stimulus' => ['transcript' => 'sheep. ... live.'], 'questions' => [
                    ['id' => '1', 'prompt' => 'Word 1', 'options' => ['ship', 'sheep'], 'answer' => 'B'],
                    ['id' => '2', 'prompt' => 'Word 2', 'options' => ['live', 'leave'], 'answer' => 'A'],
                ]]]),
            $p(['code' => 'pron.word_stress', 'name' => 'Phát âm — Trọng âm từ', 'engine' => 'choice',
                'sample' => ['instructions' => 'Choose the word whose main stress is different from the others.', 'questions' => [
                    ['id' => '1', 'options' => ['happy', 'garden', 'hotel', 'teacher'], 'answer' => 'C'],
                    ['id' => '2', 'prompt' => 'Where is the stress in "photographer"?', 'options' => ['PHO-to-gra-pher', 'pho-TO-gra-pher', 'pho-to-GRA-pher', 'pho-to-gra-PHER'], 'answer' => 'B'],
                ]]]),
            $p(['code' => 'pron.endings', 'name' => 'Phát âm — Âm cuối -s / -ed', 'engine' => 'categorize',
                'sample' => ['instructions' => 'How is the -ed ending pronounced? Put each word in the correct group.',
                    'groups' => ['/t/' => ['watched', 'stopped'], '/d/' => ['played', 'cleaned'], '/ɪd/' => ['wanted', 'needed']]]]),
            $p(['code' => 'pron.intonation', 'name' => 'Phát âm — Ngữ điệu câu hỏi', 'engine' => 'choice',
                'options' => ['choices' => ['rising' => '↗ Lên giọng', 'falling' => '↘ Xuống giọng']],
                'sample' => ['instructions' => 'Does the voice go up or down at the end?', 'questions' => [
                    ['id' => '1', 'prompt' => 'Are you a student?', 'answer' => 'rising'],
                    ['id' => '2', 'prompt' => 'Where do you live?', 'answer' => 'falling'],
                ]]]),
        ];
    }
}
