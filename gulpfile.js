var gulp = require('gulp');
var exec = require('child_process').exec;
var buildCommand = "gcc -g src/midnite-modbusd.c -o dist/bin/midnite-modbusd";
var buildCommand2 = "gcc src/newmodbus.c -o dist/bin/newmodbus";
var buildCommandPhar = "/usr/bin/env php build.php";


gulp.task('build:modbusd', function () {
    exec(buildCommand, function (error, stdout, stderr) {
        if (stdout) console.log(stdout);
        if (stderr) console.log(stderr);
    });
});

gulp.task('build:newmodbus', function () {
    exec(buildCommand2, function (error, stdout, stderr) {
        if (stdout) console.log(stdout);
        if (stderr) console.log(stderr);
    });
});

gulp.task('build:phar', function () {
    exec(buildCommandPhar, function (error, stdout, stderr) {
        if (stdout) console.log(stdout);
        if (stderr) console.log(stderr);
    });
});

gulp.task('watch', function () {
    gulp.watch('src/midnite-modbusd.c', gulp.series('build:modbusd'));
    gulp.watch('src/newmodbus.c', gulp.series('build:newmodbus'));
    gulp.watch('src/**/*.php', gulp.series('build:phar'));
});

gulp.task('build', gulp.parallel('build:newmodbus', 'build:modbusd', 'build:phar'), function () {
});

gulp.task('default', gulp.parallel('build:newmodbus', 'build:modbusd', 'build:phar', 'watch'), function () {
});
