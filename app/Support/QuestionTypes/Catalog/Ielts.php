<?php

namespace App\Support\QuestionTypes\Catalog;

/** Dạng bài IELTS (Reading, Listening, Speaking, Writing). */
class Ielts
{
    public const PASSAGE = "A  Honeybees are among the most important pollinators in the world. Around one third of the food we eat depends on pollination, and bees do much of this work.\n\n"
        ."B  Since the 1990s, beekeepers in Europe and North America have reported heavy losses of colonies. Scientists call this 'colony collapse disorder'. Pesticides, parasites such as the varroa mite and a lack of wild flowers have all been suggested as causes.\n\n"
        ."C  Some cities are now helping bees. In London, more than 3,000 hives are kept on rooftops, and Paris has banned pesticides in its parks. However, Dr Maria Lind warns that too many hives in one area may leave wild bees without enough food.\n\n"
        .'D  Farmers can also make a difference. Professor Ken Ito found that planting strips of wild flowers beside crops increased the number of bees by 40 per cent in just two years.';

    public const LIMIT_2 = ['max_words' => 2, 'numbers_free' => true];

    public static function types(): array
    {
        $r = fn (array $d) => $d + ['standard' => 'ielts', 'lang' => 'en', 'skill' => 'reading', 'scoring' => ['scale' => 'ielts_reading_academic']];
        $l = fn (array $d) => $d + ['standard' => 'ielts', 'lang' => 'en', 'skill' => 'listening', 'scoring' => ['scale' => 'ielts_listening']];
        $passage = ['title' => 'Saving the honeybee', 'passage' => self::PASSAGE];
        $talk = ['transcript' => 'Welcome to Riverside Sports Centre. The centre is open from seven a.m. to ten p.m. on weekdays. Membership costs thirty-five pounds a month, and the swimming pool is on the ground floor next to the café. Yoga classes take place on Tuesday evenings in Studio Two.'];
        $speakCriteria = ['criteria' => ['Fluency & Coherence', 'Lexical Resource', 'Grammatical Range & Accuracy', 'Pronunciation']];
        $speakScore = ['scale' => 'ielts_criteria', 'partial' => true];

        return [
            // ------------------------------------------------------------ READING
            $r(['code' => 'ielts.reading.mcq_single', 'name' => 'IELTS Reading — Multiple choice (1 đáp án)', 'engine' => 'choice',
                'sample' => ['instructions' => 'Choose the correct letter, A, B, C or D.', 'stimulus' => $passage, 'questions' => [
                    ['id' => '1', 'prompt' => 'What does the writer say about honeybees in paragraph A?', 'options' => ['They produce one third of our food.', 'They do a large part of pollination.', 'They are the only pollinators.', 'They are disappearing quickly.'], 'answer' => 'B'],
                ]]]),
            $r(['code' => 'ielts.reading.mcq_multi', 'name' => 'IELTS Reading — Multiple choice (chọn 2–3 đáp án)', 'engine' => 'multi_choice',
                'sample' => ['instructions' => 'Choose TWO letters, A–E.', 'stimulus' => $passage, 'questions' => [
                    ['id' => '1-2', 'prompt' => 'Which TWO causes of colony collapse are mentioned?', 'options' => ['pesticides', 'cold winters', 'parasites', 'air pollution', 'mobile phones'], 'answers' => ['A', 'C']],
                ]]]),
            $r(['code' => 'ielts.reading.tfng', 'name' => 'IELTS Reading — True / False / Not Given', 'engine' => 'choice',
                'options' => ['choices' => ['TRUE' => 'TRUE', 'FALSE' => 'FALSE', 'NOT GIVEN' => 'NOT GIVEN']],
                'sample' => ['instructions' => 'Do the following statements agree with the information in the passage? Write TRUE, FALSE or NOT GIVEN.', 'stimulus' => $passage, 'questions' => [
                    ['id' => '1', 'prompt' => 'Colony losses were first reported in Asia.', 'answer' => 'FALSE'],
                    ['id' => '2', 'prompt' => 'London has more rooftop hives than Paris.', 'answer' => 'NOT GIVEN'],
                    ['id' => '3', 'prompt' => 'Paris does not allow pesticides in its parks.', 'answer' => 'TRUE'],
                ]]]),
            $r(['code' => 'ielts.reading.ynng', 'name' => 'IELTS Reading — Yes / No / Not Given', 'engine' => 'choice',
                'options' => ['choices' => ['YES' => 'YES', 'NO' => 'NO', 'NOT GIVEN' => 'NOT GIVEN']],
                'description' => 'Đối chiếu với quan điểm / nhận định của tác giả.',
                'sample' => ['instructions' => 'Do the following statements agree with the claims of the writer? Write YES, NO or NOT GIVEN.', 'stimulus' => $passage, 'questions' => [
                    ['id' => '1', 'prompt' => 'Cities can play a useful role in protecting bees.', 'answer' => 'YES'],
                    ['id' => '2', 'prompt' => 'Rooftop hives are the best solution to the problem.', 'answer' => 'NO'],
                ]]]),
            $r(['code' => 'ielts.reading.matching_headings', 'name' => 'IELTS Reading — Matching headings', 'engine' => 'matching',
                'sample' => ['instructions' => 'Choose the correct heading for each paragraph from the list of headings.', 'stimulus' => $passage, 'options_title' => 'List of Headings',
                    'items' => [['id' => '1', 'text' => 'Paragraph B'], ['id' => '2', 'text' => 'Paragraph C'], ['id' => '3', 'text' => 'Paragraph D']],
                    'options' => [['key' => 'i', 'text' => 'Why bees matter'], ['key' => 'ii', 'text' => 'A mysterious decline'], ['key' => 'iii', 'text' => 'Urban action and its risks'], ['key' => 'iv', 'text' => 'A simple solution in the countryside'], ['key' => 'v', 'text' => 'The history of beekeeping']],
                    'answers' => ['1' => 'ii', '2' => 'iii', '3' => 'iv']]]),
            $r(['code' => 'ielts.reading.matching_information', 'name' => 'IELTS Reading — Matching information (đoạn nào chứa thông tin)', 'engine' => 'matching', 'options' => ['reuse' => true],
                'sample' => ['instructions' => 'Which paragraph contains the following information? You may use any letter more than once.', 'stimulus' => $passage,
                    'items' => [['id' => '1', 'text' => 'a percentage increase in bee numbers'], ['id' => '2', 'text' => 'a possible negative effect of keeping many hives'], ['id' => '3', 'text' => 'how much of our food relies on pollination']],
                    'options' => [['key' => 'A', 'text' => 'Paragraph A'], ['key' => 'B', 'text' => 'Paragraph B'], ['key' => 'C', 'text' => 'Paragraph C'], ['key' => 'D', 'text' => 'Paragraph D']],
                    'answers' => ['1' => 'D', '2' => 'C', '3' => 'A']]]),
            $r(['code' => 'ielts.reading.matching_features', 'name' => 'IELTS Reading — Matching features (nối nhận định ↔ người / mốc)', 'engine' => 'matching', 'options' => ['reuse' => true],
                'sample' => ['instructions' => 'Match each statement with the correct person.', 'stimulus' => $passage, 'options_title' => 'List of People',
                    'items' => [['id' => '1', 'text' => 'Wild flowers near farms can quickly help bees.'], ['id' => '2', 'text' => 'Wild bees may suffer from competition.']],
                    'options' => [['key' => 'A', 'text' => 'Dr Maria Lind'], ['key' => 'B', 'text' => 'Professor Ken Ito']], 'answers' => ['1' => 'B', '2' => 'A']]]),
            $r(['code' => 'ielts.reading.matching_endings', 'name' => 'IELTS Reading — Matching sentence endings', 'engine' => 'matching',
                'sample' => ['instructions' => 'Complete each sentence with the correct ending, A–E.', 'stimulus' => $passage,
                    'items' => [['id' => '1', 'text' => 'Colony collapse disorder'], ['id' => '2', 'text' => 'Strips of wild flowers']],
                    'options' => ['increased bee numbers on farms.', 'has been reported since the 1990s.', 'is banned in Paris.', 'produce most of our food.', 'were introduced in London.'],
                    'answers' => ['1' => 'B', '2' => 'A']]]),
            $r(['code' => 'ielts.reading.sentence_completion', 'name' => 'IELTS Reading — Sentence completion', 'engine' => 'gap_text', 'options' => self::LIMIT_2,
                'sample' => ['instructions' => 'Complete the sentences. Choose NO MORE THAN TWO WORDS AND/OR A NUMBER from the passage.', 'stimulus' => $passage,
                    'text' => "1. In London, over [[1]] hives are kept on roofs.\n2. The varroa mite is a type of [[2]].",
                    'answers' => ['1' => ['3,000', '3000'], '2' => ['parasite']]]]),
            $r(['code' => 'ielts.reading.summary_completion', 'name' => 'IELTS Reading — Summary / note completion (không có ngân hàng từ)', 'engine' => 'gap_text', 'options' => ['max_words' => 1],
                'sample' => ['instructions' => 'Complete the summary. Choose ONE WORD ONLY from the passage.', 'stimulus' => $passage,
                    'text' => 'Bee colonies have been lost since the 1990s. Possible causes include pesticides, parasites and a shortage of wild [[1]]. Farmers who plant flowers next to their [[2]] can increase bee numbers.',
                    'answers' => ['1' => ['flowers'], '2' => ['crops']]]]),
            $r(['code' => 'ielts.reading.summary_bank', 'name' => 'IELTS Reading — Summary completion (có ngân hàng từ)', 'engine' => 'gap_bank',
                'sample' => ['instructions' => 'Complete the summary using the list of words below.', 'stimulus' => $passage,
                    'text' => 'Cities such as London keep hives on [[1]], while Paris has stopped the use of [[2]] in parks.',
                    'bank' => ['rooftops', 'pesticides', 'farms', 'parasites', 'gardens'], 'answers' => ['1' => 'rooftops', '2' => 'pesticides']]]),
            $r(['code' => 'ielts.reading.table_completion', 'name' => 'IELTS Reading — Table completion', 'engine' => 'gap_text', 'options' => self::LIMIT_2,
                'sample' => ['instructions' => 'Complete the table. Write NO MORE THAN TWO WORDS AND/OR A NUMBER.', 'stimulus' => $passage,
                    'text' => "Place | Action | Result\nLondon | hives on rooftops | over [[1]] hives\nParis | ban on [[2]] in parks | —\nFarms | strips of [[3]] | 40% more bees",
                    'answers' => ['1' => ['3,000'], '2' => ['pesticides'], '3' => ['wild flowers', 'flowers']]]]),
            $r(['code' => 'ielts.reading.flowchart', 'name' => 'IELTS Reading — Flow-chart completion', 'engine' => 'gap_text', 'options' => ['max_words' => 1],
                'sample' => ['instructions' => 'Complete the flow-chart. Write ONE WORD ONLY.', 'stimulus' => $passage,
                    'text' => "Farmers plant wild flowers beside [[1]]\n↓\nMore bees visit the area\n↓\nBee numbers rise by 40 per cent within two [[2]]",
                    'answers' => ['1' => ['crops'], '2' => ['years']]]]),
            $r(['code' => 'ielts.reading.diagram_label', 'name' => 'IELTS Reading — Diagram label completion', 'engine' => 'gap_text', 'options' => ['max_words' => 1],
                'sample' => ['instructions' => 'Label the diagram. Write ONE WORD ONLY.',
                    'stimulus' => ['image' => '/images/qtypes/diagram.svg', 'passage' => 'Water is collected from the roof and flows through a pipe into a filter. Clean water is stored in a tank and is then pumped to the house.'],
                    'text' => "1. Water is collected from the [[1]].\n2. It passes through a [[2]].\n3. It is stored in a [[3]].",
                    'answers' => ['1' => ['roof'], '2' => ['filter'], '3' => ['tank']]]]),
            $r(['code' => 'ielts.reading.short_answer', 'name' => 'IELTS Reading — Short-answer questions', 'engine' => 'short_text', 'options' => ['max_words' => 3, 'numbers_free' => true],
                'sample' => ['instructions' => 'Answer the questions. Write NO MORE THAN THREE WORDS AND/OR A NUMBER.', 'stimulus' => $passage, 'questions' => [
                    ['id' => '1', 'prompt' => 'What name do scientists give to the loss of colonies?', 'answers' => ['colony collapse disorder']],
                    ['id' => '2', 'prompt' => 'By what percentage did bee numbers increase?', 'answers' => ['40 per cent', '40%', '40 percent', '40']],
                ]]]),

            // ------------------------------------------------------------ LISTENING
            $l(['code' => 'ielts.listening.form_completion', 'name' => 'IELTS Listening — Form / note completion', 'engine' => 'gap_text', 'options' => self::LIMIT_2,
                'sample' => ['instructions' => 'Complete the form. Write NO MORE THAN TWO WORDS AND/OR A NUMBER.', 'stimulus' => $talk,
                    'text' => "RIVERSIDE SPORTS CENTRE\nWeekday opening hours: 7 a.m. – [[1]]\nMonthly membership: £[[2]]\nYoga: Tuesday evenings in [[3]]",
                    'answers' => ['1' => ['10 p.m.', '10 pm', '10pm', '10'], '2' => ['35'], '3' => ['Studio Two', 'Studio 2']]]]),
            $l(['code' => 'ielts.listening.table_completion', 'name' => 'IELTS Listening — Table completion', 'engine' => 'gap_text', 'options' => self::LIMIT_2,
                'sample' => ['instructions' => 'Complete the table. Write NO MORE THAN TWO WORDS AND/OR A NUMBER.', 'stimulus' => $talk,
                    'text' => "Facility | Location\nSwimming pool | [[1]] floor\nCafé | next to the [[2]]",
                    'answers' => ['1' => ['ground'], '2' => ['swimming pool', 'pool']]]]),
            $l(['code' => 'ielts.listening.mcq', 'name' => 'IELTS Listening — Multiple choice', 'engine' => 'choice',
                'sample' => ['instructions' => 'Choose the correct letter, A, B or C.', 'stimulus' => $talk, 'questions' => [
                    ['id' => '1', 'prompt' => 'When do yoga classes take place?', 'options' => ['Monday mornings', 'Tuesday evenings', 'at weekends'], 'answer' => 'B'],
                ]]]),
            $l(['code' => 'ielts.listening.matching', 'name' => 'IELTS Listening — Matching', 'engine' => 'matching',
                'sample' => ['instructions' => 'What is located in each place? Choose the correct letter.', 'stimulus' => $talk,
                    'items' => [['id' => '1', 'text' => 'Ground floor'], ['id' => '2', 'text' => 'Studio Two']],
                    'options' => ['yoga classes', 'swimming pool', 'tennis courts'], 'answers' => ['1' => 'B', '2' => 'A']]]),
            $l(['code' => 'ielts.listening.map_labelling', 'name' => 'IELTS Listening — Map / plan labelling', 'engine' => 'matching',
                'sample' => ['instructions' => 'Label the map. Write the correct letter, A–E.',
                    'stimulus' => ['image' => '/images/qtypes/map.svg', 'transcript' => 'The library is in the top left corner of the park. The café is near the bottom right, beside the lake. The car park is at the bottom left.',
                        'markers' => [['key' => 'A', 'x' => 20, 'y' => 25], ['key' => 'B', 'x' => 55, 'y' => 20], ['key' => 'C', 'x' => 75, 'y' => 70], ['key' => 'D', 'x' => 18, 'y' => 78], ['key' => 'E', 'x' => 48, 'y' => 52]]],
                    'items' => [['id' => '1', 'text' => 'Library'], ['id' => '2', 'text' => 'Café'], ['id' => '3', 'text' => 'Car park']],
                    'options' => ['A', 'B', 'C', 'D', 'E'], 'answers' => ['1' => 'A', '2' => 'C', '3' => 'D']]]),
            $l(['code' => 'ielts.listening.sentence_completion', 'name' => 'IELTS Listening — Sentence completion', 'engine' => 'gap_text', 'options' => ['max_words' => 1, 'numbers_free' => true],
                'sample' => ['instructions' => 'Complete the sentences. Write ONE WORD AND/OR A NUMBER.', 'stimulus' => $talk,
                    'text' => 'The swimming pool is next to the [[1]].', 'answers' => ['1' => ['café', 'cafe']]]]),
            $l(['code' => 'ielts.listening.short_answer', 'name' => 'IELTS Listening — Short answer', 'engine' => 'short_text', 'options' => self::LIMIT_2,
                'sample' => ['instructions' => 'Answer the question. Write NO MORE THAN TWO WORDS AND/OR A NUMBER.', 'stimulus' => $talk, 'questions' => [
                    ['id' => '1', 'prompt' => 'How much is the monthly membership?', 'answers' => ['£35', '35 pounds', 'thirty-five pounds', '35']],
                ]]]),

            // ------------------------------------------------------------ SPEAKING
            ['code' => 'ielts.speaking.part1', 'name' => 'IELTS Speaking — Part 1 (câu hỏi ngắn)', 'standard' => 'ielts', 'lang' => 'en', 'skill' => 'speaking', 'engine' => 'speaking', 'grading' => 'ai',
                'options' => $speakCriteria + ['speak_seconds' => 30], 'scoring' => $speakScore,
                'sample' => ['instructions' => 'Answer each question in 2–3 sentences.', 'questions' => ['Do you work or are you a student?', 'What do you like about your hometown?', 'How often do you use public transport?']]],
            ['code' => 'ielts.speaking.part2', 'name' => 'IELTS Speaking — Part 2 (thẻ chủ đề, 1 phút chuẩn bị, 2 phút nói)', 'standard' => 'ielts', 'lang' => 'en', 'skill' => 'speaking', 'engine' => 'speaking', 'grading' => 'ai',
                'options' => $speakCriteria + ['prep_seconds' => 60, 'speak_seconds' => 120], 'scoring' => $speakScore,
                'sample' => ['cue_card' => ['topic' => 'Describe a place you visited that you really enjoyed.', 'points' => ['where it was', 'when you went there', 'what you did there', 'and explain why you enjoyed it']]]],
            ['code' => 'ielts.speaking.part3', 'name' => 'IELTS Speaking — Part 3 (thảo luận)', 'standard' => 'ielts', 'lang' => 'en', 'skill' => 'speaking', 'engine' => 'speaking', 'grading' => 'ai',
                'options' => $speakCriteria + ['speak_seconds' => 90], 'scoring' => $speakScore,
                'sample' => ['questions' => ['Why do people like to travel to other countries?', 'How has tourism changed in your country in recent years?', 'Can tourism have negative effects on local communities?']]],

            // ------------------------------------------------------------ WRITING
            ['code' => 'ielts.writing.task1_academic', 'name' => 'IELTS Writing — Task 1 Academic (biểu đồ / quy trình / bản đồ)', 'standard' => 'ielts', 'lang' => 'en', 'skill' => 'writing', 'engine' => 'writing', 'grading' => 'ai',
                'options' => ['criteria' => ['Task Achievement', 'Coherence & Cohesion', 'Lexical Resource', 'Grammatical Range & Accuracy']], 'scoring' => $speakScore,
                'sample' => ['prompt' => 'The bar chart shows the number of visitors to three museums in 2010 and 2020. Summarise the information by selecting and reporting the main features, and make comparisons where relevant. Write at least 150 words.',
                    'stimulus' => ['image' => '/images/qtypes/chart.svg'], 'min_words' => 150, 'time_minutes' => 20]],
            ['code' => 'ielts.writing.task1_general', 'name' => 'IELTS Writing — Task 1 General (viết thư)', 'standard' => 'ielts', 'lang' => 'en', 'skill' => 'writing', 'engine' => 'writing', 'grading' => 'ai',
                'options' => ['criteria' => ['Task Achievement', 'Coherence & Cohesion', 'Lexical Resource', 'Grammatical Range & Accuracy']], 'scoring' => $speakScore,
                'sample' => ['prompt' => "You recently stayed at a hotel and left something in your room. Write a letter to the hotel manager. In your letter:\n• describe what you left\n• explain where you think you left it\n• say what you would like the manager to do\nWrite at least 150 words.", 'min_words' => 150, 'time_minutes' => 20]],
            ['code' => 'ielts.writing.task2', 'name' => 'IELTS Writing — Task 2 (nghị luận)', 'standard' => 'ielts', 'lang' => 'en', 'skill' => 'writing', 'engine' => 'writing', 'grading' => 'ai',
                'options' => ['criteria' => ['Task Response', 'Coherence & Cohesion', 'Lexical Resource', 'Grammatical Range & Accuracy']], 'scoring' => $speakScore,
                'sample' => ['prompt' => 'Some people believe that university education should be free for everyone. Others think students should pay for it. Discuss both views and give your own opinion. Write at least 250 words.', 'min_words' => 250, 'time_minutes' => 40]],
        ];
    }
}
