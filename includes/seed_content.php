<?php
declare(strict_types=1);

function seedSectionContent(int $id): array
{
    $map = [
        1  => _preTestContent(),
        2  => _motivationContent(),
        3  => _introILOsContent(),
        4  => _topic1Content(),
        5  => _fa1Content(),
        6  => _topic2Content(),
        7  => _fa2Content(),
        8  => _topic3Content(),
        9  => _fa3Content(),
        10 => _topic4Content(),
        11 => _fa4Content(),
        12 => _postTestContent(),
        13 => _evaluationContent(),
    ];
    return $map[$id] ?? ['type' => 'lesson', 'title' => 'Section ' . $id, 'html' => '<p>Content coming soon.</p>'];
}

function _preTestContent(): array
{
    return [
        'type'      => 'quiz',
        'title'     => 'Pre-test',
        'direction' => 'Choose the letter of the best answer.',
        'questions' => [
            ['text' => 'Which keyword is used in Python to define a class?',
             'options' => ['def', 'class', 'object', 'function'], 'correct' => 1],
            ['text' => 'What is an object in OOP?',
             'options' => ['A function', 'A variable', 'An instance of a class', 'A data type'], 'correct' => 2],
            ['text' => 'Which OOP concept allows a class to inherit properties from another class?',
             'options' => ['Encapsulation', 'Polymorphism', 'Abstraction', 'Inheritance'], 'correct' => 3],
            ['text' => 'Which of the following best describes encapsulation?',
             'options' => ['A method that returns multiple values', 'Hiding internal details and exposing only necessary parts', 'Creating multiple objects from one class', 'Using the same method name in different classes'], 'correct' => 1],
            ['text' => 'What does polymorphism mean in OOP?',
             'options' => ['Having many classes', 'Using different data types', 'The same interface producing different behaviors', 'Combining two classes into one'], 'correct' => 2],
        ],
    ];
}

function _motivationContent(): array
{
    require_once __DIR__ . '/seed_lessons.php';
    return ['type' => 'lesson', 'title' => 'Motivation', 'html' => lessonHtml(2) ?? ''];
}

function _introILOsContent(): array
{
    require_once __DIR__ . '/seed_lessons.php';
    return ['type' => 'lesson', 'title' => 'Introduction & Intended Learning Outcomes', 'html' => lessonHtml(3) ?? ''];
}

function _topic1Content(): array
{
    require_once __DIR__ . '/seed_lessons.php';
    return ['type' => 'lesson', 'title' => 'Topic 1: Introduction to OOP', 'html' => lessonHtml(4) ?? ''];
}

function _fa1Content(): array
{
    return [
        'type'      => 'assessment',
        'title'     => 'Formative Assessment 1',
        'direction' => 'Choose the letter of the best answer.',
        'questions' => [
            ['part' => 'Part I – Programming Paradigms',
             'text' => 'Which programming paradigm focuses on procedures or step-by-step instructions?',
             'options' => ['Functional Programming','Object-Oriented Programming','Procedural Programming','Event-Driven Programming'],
             'correct' => 2,
             'explanation' => 'Procedural Programming organizes code as step-by-step instructions. Writing a recipe listing steps to bake a cake is a classic analogy.'],
            ['text' => 'Which programming paradigm focuses mainly on mathematical functions?',
             'options' => ['Procedural Programming','Functional Programming','Object-Oriented Programming','Structured Programming'],
             'correct' => 1,
             'explanation' => 'Functional Programming treats computation as the evaluation of mathematical functions. It is widely used in data science and AI.'],
            ['text' => 'Which programming paradigm focuses on objects that represent real-world things?',
             'options' => ['Functional Programming','Procedural Programming','Object-Oriented Programming','Sequential Programming'],
             'correct' => 2,
             'explanation' => 'OOP organizes code around objects that model real-world entities. It is the most widely used paradigm in modern software.'],
            ['text' => 'Which situation best represents Procedural Programming?',
             'options' => ['Creating objects such as Student and Teacher','Using mathematical functions to process data','Following a series of steps to complete a task','Creating properties and methods for an object'],
             'correct' => 2,
             'explanation' => 'Procedural Programming is characterized by sequences of instructions — like following a recipe step by step.'],
            ['text' => 'Which programming paradigm is represented by languages such as Java, C++, and Python?',
             'options' => ['Procedural Programming','Functional Programming','Object-Oriented Programming','Assembly Programming'],
             'correct' => 2,
             'explanation' => 'Java, C++, and Python all support OOP as a primary paradigm.'],
            ['part' => 'Part II – Basic Concepts of OOP',
             'text' => 'In OOP, what serves as a blueprint or template for creating objects?',
             'options' => ['Method','Property','Object','Class'],
             'correct' => 3,
             'explanation' => 'A Class is the blueprint that defines the structure and behavior of its objects.'],
            ['text' => 'Juan, Maria, and Alex are BSIT students created from the Student class. What are they?',
             'options' => ['Methods','Objects','Properties','Classes'],
             'correct' => 1,
             'explanation' => 'Juan, Maria, and Alex are Objects (instances) of the Student class.'],
            ['text' => 'Which of the following is a property of a Student object?',
             'options' => ['study()','take_exam()','age','submit_project()'],
             'correct' => 2,
             'explanation' => 'Properties (attributes) store data about an object. `age` is a data value; the others are methods.'],
            ['text' => 'Which of the following is a method of a Student object?',
             'options' => ['name','age','grade','study()'],
             'correct' => 3,
             'explanation' => 'Methods define what an object can do. `study()` is an action; name, age, grade are properties.'],
            ['text' => "Given: Class=Student, Objects=Juan/Maria/Alex, Properties=name/age/grade, Methods=study()/take_exam()/submit_project()\n\nWhich statement is correct?",
             'options' => ['Student is an object, while Juan is a class.','age is a method because it describes what a student can do.','study() is a property because it stores student information.','Student is a class, while Juan, Maria, and Alex are objects.'],
             'correct' => 3,
             'explanation' => 'Student is the Class (blueprint); Juan, Maria, and Alex are Objects (instances). `age` is a property; `study()` is a method.'],
        ],
    ];
}

function _topic2Content(): array
{
    require_once __DIR__ . '/seed_lessons.php';
    return ['type' => 'lesson', 'title' => 'Topic 2: Inheritance', 'html' => lessonHtml(6) ?? ''];
}

function _fa2Content(): array
{
    return [
        'type'      => 'assessment',
        'title'     => 'Formative Assessment 2',
        'direction' => 'Choose the letter of the best answer.',
        'questions' => [
            ['text' => 'Which Python function calls the parent class constructor from inside a child class?',
             'options' => ['parent().__init__()','base.__init__(self)','super().__init__()','inherit().__init__()'],
             'correct' => 2,
             'explanation' => '`super().__init__()` is the correct, Pythonic way to call the parent constructor using the Method Resolution Order (MRO).'],
            ['text' => 'What is method overriding in Object-Oriented Programming?',
             'options' => ['Deleting a method from the parent class','Calling a method from two classes simultaneously','When a child class provides its own implementation of a parent method','Creating a method that accepts unlimited arguments'],
             'correct' => 2,
             'explanation' => 'Method overriding occurs when a child class redefines a method from the parent. Python uses the child version when called on a child instance.'],
            ['text' => 'Which best demonstrates polymorphism in Python?',
             'options' => ['Defining two classes with identical methods','Calling the same method name on different object types, each responding differently','Creating multiple instances of the same class','Using isinstance() to check the type of an object'],
             'correct' => 1,
             'explanation' => 'Polymorphism: different classes implementing the same method name but behaving differently.'],
        ],
    ];
}

function _topic3Content(): array
{
    require_once __DIR__ . '/seed_lessons.php';
    return ['type' => 'lesson', 'title' => 'Topic 3: Encapsulation', 'html' => lessonHtml(8) ?? ''];
}

function _fa3Content(): array
{
    return [
        'type'      => 'assessment',
        'title'     => 'Formative Assessment 3',
        'direction' => 'Choose the letter of the best answer.',
        'questions' => [
            ['text' => 'How do you make an instance attribute private (name-mangled) in Python?',
             'options' => ['Use a single underscore: `_balance`','Use a double underscore: `__balance`','Write in ALL_CAPS: `BALANCE`','Decorate with `@private`'],
             'correct' => 1,
             'explanation' => 'Double underscore (`__balance`) triggers name-mangling, transforming the attribute to `_ClassName__balance`.'],
            ['text' => 'What is the purpose of a getter method in an encapsulated Python class?',
             'options' => ['To permanently delete a private attribute','To override an inherited method','To provide controlled, read-only access to a private attribute','To create a new instance of the class'],
             'correct' => 2,
             'explanation' => 'A getter provides controlled access to read a private attribute value without exposing it directly.'],
            ['text' => 'Which module and decorator create abstract classes and abstract methods in Python?',
             'options' => ['`abstract` module and `@override`','`abc` module and `@abstractmethod`','`interface` module and `@abstract`','`typing` module and `@abstractclass`'],
             'correct' => 1,
             'explanation' => 'The `abc` module and `@abstractmethod` decorator are used. Child classes must implement all abstract methods.'],
        ],
    ];
}

function _topic4Content(): array
{
    require_once __DIR__ . '/seed_lessons.php';
    return ['type' => 'lesson', 'title' => 'Topic 4: Polymorphism', 'html' => lessonHtml(10) ?? ''];
}

function _fa4Content(): array
{
    return [
        'type'      => 'assessment',
        'title'     => 'Formative Assessment 4',
        'direction' => 'Choose the letter of the best answer.',
        'questions' => [
            ['text' => 'What does polymorphism allow in OOP?',
             'options' => ['Multiple inheritance only','Different classes to respond to the same method in their own way','Strict type checking at compile time','Preventing method overriding'],
             'correct' => 1,
             'explanation' => 'Polymorphism enables the same method name to produce different behaviors across different object types.'],
            ['text' => "Python's duck typing means:",
             'options' => ['You must declare types for all variables','If an object has the required method, it can be used regardless of its class','Only certain classes support polymorphism','Type checking happens at compile time'],
             'correct' => 1,
             'explanation' => '"If it walks like a duck and quacks like a duck, it\'s a duck." Python checks for the needed methods at runtime.'],
            ['text' => 'Which best demonstrates runtime polymorphism?',
             'options' => ['Defining two functions with the same name in the same class','Calling .area() on a list of mixed Circle and Rectangle objects','Creating multiple instances of the same class','Using type annotations in function signatures'],
             'correct' => 1,
             'explanation' => 'Calling `.area()` on a mixed list of shapes demonstrates runtime polymorphism — each object responds differently based on its actual type.'],
        ],
    ];
}

function _postTestContent(): array
{
    return [
        'type'      => 'quiz',
        'title'     => 'Post-test',
        'direction' => 'Choose the letter of the best answer.',
        'questions' => [
            ['text' => 'What is a class in Python OOP?',
             'options' => ['A built-in function','A blueprint or template for creating objects','A type of variable','A module import'], 'correct' => 1],
            ['text' => 'Which keyword initializes a new object in Python?',
             'options' => ['self','init','__init__','new'], 'correct' => 2],
            ['text' => 'Which OOP concept does `class Cat(Animal):` demonstrate?',
             'options' => ['Encapsulation','Polymorphism','Inheritance','Abstraction'], 'correct' => 2],
            ['text' => 'A double underscore prefix on an attribute (`__balance`) implements:',
             'options' => ['Inheritance','Polymorphism','Encapsulation (name-mangling)','Abstraction'], 'correct' => 2],
            ['text' => 'Calling `.area()` on different shape objects producing different results demonstrates:',
             'options' => ['Inheritance','Encapsulation','Abstraction','Polymorphism'], 'correct' => 3],
        ],
    ];
}

function _evaluationContent(): array
{
    return [
        'type'      => 'evaluation',
        'title'     => 'Course Evaluation',
        'direction' => 'Select the option that best reflects your experience.',
        'questions' => [
            ['text' => 'How would you rate the overall quality of this module?',
             'options' => ['Excellent','Good','Fair','Needs improvement'], 'correct' => -1],
            ['text' => 'How clear were the lesson explanations?',
             'options' => ['Very clear','Mostly clear','Somewhat clear','Unclear'], 'correct' => -1],
            ['text' => 'How helpful were the formative assessments?',
             'options' => ['Very helpful','Helpful','Neutral','Not helpful'], 'correct' => -1],
            ['text' => 'Would you recommend this module to other students?',
             'options' => ['Definitely','Probably','Probably not','Definitely not'], 'correct' => -1],
        ],
    ];
}
