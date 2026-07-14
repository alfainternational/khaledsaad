allprojects {
    repositories {
        google()
        mavenCentral()
    }
}

val newBuildDir: Directory =
    rootProject.layout.buildDirectory
        .dir("../../build")
        .get()
rootProject.layout.buildDirectory.value(newBuildDir)

subprojects {
    val newSubprojectBuildDir: Directory = newBuildDir.dir(project.name)
    project.layout.buildDirectory.value(newSubprojectBuildDir)
}
// افرض compileSdk 36 على مكتبات الإضافات بعد تقييم كلٍّ منها مباشرةً (وقبل قراءة
// AGP لها). مُسجَّل قبل evaluationDependsOn كي يلتقط التقييم المتسلسل في وقته.
// السبب: file_picker يضبط نفسه على 34 بينما تبعيته lifecycle تتطلّب 36.
subprojects {
    afterEvaluate {
        extensions.findByType(com.android.build.gradle.LibraryExtension::class.java)
            ?.let { it.compileSdk = 36 }
    }
}
subprojects {
    project.evaluationDependsOn(":app")
}

tasks.register<Delete>("clean") {
    delete(rootProject.layout.buildDirectory)
}
