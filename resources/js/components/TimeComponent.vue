<style scoped>

</style>

<template>
    <div class="container">
        <div class="row justify-content-center bg-light" style="height: 500px">
            <div class="align-self-center">
                <table class="table table-striped">
                    <thead>
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">Time</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="(item, key) in time_list" :key="key">
                        <th scope="row">{{ key + 1 }}</th>
                        <td>{{ item }}</td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script>
const MAX_TIMES = 50;

export default {
    data() {
        const initialTimes = typeof this.init_data === 'string' ? JSON.parse(this.init_data) : (this.init_data || []);

        return {
            time_list: initialTimes.slice(-MAX_TIMES)
        }
    },
    props: ['init_data'],
    mounted() {
        if (window.Echo) {
            window.Echo.channel('push-time')
                .listen('PushTimeEvent', (e) => {
                    this.time_list.push(e.time);
                    if (this.time_list.length > MAX_TIMES) {
                        this.time_list.splice(0, this.time_list.length - MAX_TIMES);
                    }
                });
        }
    },
    beforeUnmount() {
        window.Echo?.leaveChannel('push-time');
    }
}
</script>
